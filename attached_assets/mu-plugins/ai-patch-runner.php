<?php
/**
 * Plugin Name: AI Patch Runner (MU) — v2.1.0
 * Description: Robust patch runner with snapshots (Revert), dry-run, multi-root targeting, regex search/replace, and AI free-form tasks. Drop into wp-content/mu-plugins/.
 * Author: Ken + Helper
 * Version: 2.1.0
 *
 * SECURITY MODEL:
 * - By default, edits are constrained to WP_CONTENT_DIR (themes, plugins, mu-plugins, uploads).
 * - You may enable explicit access to ABSPATH files (e.g., wp-config.php, .htaccess) per operation.
 * - Every write creates a timestamped snapshot under uploads/ai-patch-runner/snapshots/<id>/ with a manifest.json and before-blobs.
 * - Revert can roll back whole snapshots or selected files; reverts also snapshot the pre-revert state.
 * - Dry-run shows diffs without writing. Admin UI uses nonces + capability checks. WP-CLI mirrors features.
 */

if (!defined('ABSPATH')) exit;

define('AI_PR_VERSION', '2.1.0');
define('AI_PR_PLUGIN_SLUG', 'ai-patch-runner');
define('AI_PR_SNAP_ROOT', 'ai-patch-runner/snapshots');      // under uploads basedir
define('AI_PR_LOG_ROOT',  'ai-patch-runner/logs');            // under uploads basedir
define('AI_PR_DEFAULT_EXT_REGEX', '/\.(php|js|css|scss|sass|ts|tsx|json|yml|yaml|xml|html?|txt|md|ini|conf|htaccess)$/i');

/* =========================
 * Config/Allowlists (filters)
 * ========================= */
function ai_pr_allowed_ext_regex() {
    return apply_filters('ai_pr_allowed_ext_regex', AI_PR_DEFAULT_EXT_REGEX);
}
function ai_pr_allowed_roots() {
    // Returned as [ 'label' => [ 'path' => ..., 'enabled' => bool ] ]
    $roots = [
        'wp-content'   => ['path' => WP_CONTENT_DIR, 'enabled' => true],
        'mu-plugins'   => ['path' => defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins', 'enabled' => true],
        'plugins'      => ['path' => defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins',  'enabled' => true],
        'themes'       => ['path' => function_exists('get_theme_root') ? get_theme_root() : WP_CONTENT_DIR . '/themes', 'enabled' => true],
        'uploads'      => ['path' => wp_upload_dir()['basedir'], 'enabled' => true],
        // Requires explicit per-run opt-in via UI or --allow-core
        'wordpress-root (core-sensitive)' => ['path' => ABSPATH, 'enabled' => false],
    ];
    // Normalize function-returned paths (themes)
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
        // Treat as relative to WP_CONTENT_DIR by default
        $path = WP_CONTENT_DIR . '/' . ltrim($relOrAbs, '/');
    }
    $real = realpath($path);
    $path = $real ? $real : $path; // may not exist yet
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
        'files'     => [], // abs => [exists_before, checksum_before, size_before]
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
 * Block parsing (general)
 * =======================

Supported blocks:

=== FILE: relative/or/absolute/path ===
<full file content>
=== END FILE ===

=== APPEND: path ===
<append content, or paired token region if token provided>
=== END APPEND ===

=== DELETE: path ===
=== END DELETE ===

=== RENAME: oldpath => newpath ===
=== END RENAME ===

Optional tokenized regions for APPEND:
<!-- AI:start:NAME --> ... <!-- AI:end:NAME -->
*/
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
    // No region found, append region
    $block = "\n$start\n" . rtrim($replacement) . "\n$end\n";
    return rtrim($original) . $block;
}

/* =========
 * Admin UI
 * ========= */
add_action('admin_menu', function(){
    add_management_page(
        'AI Patch Runner',
        'AI Patch Runner',
        'manage_options',
        AI_PR_PLUGIN_SLUG,
        'ai_pr_admin_page'
    );
});

/* Admin JS: toggle Apply button based on Dry-run + input presence */
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'tools_page_' . AI_PR_PLUGIN_SLUG) return;

    wp_register_script('ai-pr-admin', false, [], AI_PR_VERSION, true);
    $inline = <<<'JS'
    (function(){
      function $(sel){ return document.querySelector(sel); }
      function $all(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }

      function mode(){
        var r = $all('input[name="source_mode"]');
        for (var i=0;i<r.length;i++){ if (r[i].checked) return r[i].value; }
        return 'blocks';
      }
      function hasInput(){
        var m = mode();
        var blocks = $('textarea[name="blocks"]');
        var task   = $('textarea[name="task"]');
        if (m === 'ai') return task && task.value.trim().length > 0;
        return blocks && blocks.value.trim().length > 0;
      }
      function refresh(){
        var dry   = $('#ai_pr_dry_run');
        var apply = $('#ai_pr_apply_btn');
        if (!apply) return;
        var enable = hasInput();
        apply.disabled = !enable;
        apply.setAttribute('aria-disabled', (!enable).toString());
      }
      function bind(){
        $all('textarea[name="blocks"], textarea[name="task"]').forEach(function(el){
          el.addEventListener('input', refresh);
          el.addEventListener('change', refresh);
        });
        $all('input[name="source_mode"]').forEach(function(el){
          el.addEventListener('change', refresh);
        });
        // Make Preview always set Dry-run ON before submit
        var preview = $('#ai_pr_preview_btn');
        if (preview) {
          preview.addEventListener('click', function(){ 
            var dry = $('#ai_pr_dry_run');
            if (dry) dry.checked = true; 
            refresh(); 
          });
        }
        refresh();
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
      } else {
        bind();
      }
    })();
    JS;
    wp_enqueue_script('ai-pr-admin');
    wp_add_inline_script('ai-pr-admin', $inline);
});

/* Main admin page */
function ai_pr_admin_page() {
    if (!current_user_can('manage_options')) return;
    $nonce_action = 'ai_pr_action_' . get_current_user_id();
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'apply';

    echo '<div class="wrap"><h1>AI Patch Runner</h1>';
    echo '<h2 class="nav-tab-wrapper">';
    foreach ([
        'apply'     => 'Apply Patch',
        'search'    => 'Search & Replace',
        'revert'    => 'Revert Snapshot',
        'snapshots' => 'Snapshots',
        'about'     => 'About'
    ] as $t => $label) {
        $cls = $tab === $t ? ' nav-tab nav-tab-active' : ' nav-tab';
        echo '<a class="'.$cls.'" href="'.esc_url(admin_url('tools.php?page='.AI_PR_PLUGIN_SLUG.'&tab='.$t)).'">'.esc_html($label).'</a>';
    }
    echo '</h2>';

    if ($tab === 'apply') {
        ai_pr_ui_apply_patch($nonce_action);
    } elseif ($tab === 'search') {
        ai_pr_ui_search_replace($nonce_action);
    } elseif ($tab === 'revert') {
        ai_pr_ui_revert($nonce_action);
    } elseif ($tab === 'snapshots') {
        ai_pr_ui_snapshots();
    } else {
        ai_pr_ui_about();
    }
    echo '</div>';
}

/* ============================
 * UI: Apply Patch (blocks/AI)
 * ============================ */
function ai_pr_ui_apply_patch($nonce_action) {
    // Inputs
    $task        = isset($_POST['task']) ? wp_unslash($_POST['task']) : '';
    $blocks_text = isset($_POST['blocks']) ? wp_unslash($_POST['blocks']) : '';
    $token_name  = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    $mode_source = isset($_POST['source_mode']) ? sanitize_text_field($_POST['source_mode']) : 'blocks';
    $label       = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';

    // Flags
    $allow_core  = !empty($_POST['allow_core']);
    $dry_run     = !empty($_POST['dry_run']); // UI checkbox state

    $do_preview  = !empty($_POST['do_preview']);
    $do_apply    = !empty($_POST['do_apply']);

    if (($do_preview || $do_apply) && check_admin_referer($nonce_action)) {
        $error   = '';
        $preview = '';
        $blocks  = [];

        // Source: AI or provided blocks
        if ($mode_source === 'ai') {
            if (!function_exists('openai_wp_chat')) {
                echo '<div class="notice notice-error"><p><strong>AI bridge openai_wp_chat() not found.</strong></p></div>';
                return;
            }
            $prompt = "Emit file operations using standardized blocks (FILE/APPEND/DELETE/RENAME). Only include files we truly must modify.\n\nUser Task:\n" . $task;
            $res = openai_wp_chat('system:You are a careful file patcher. Use conservative edits and minimal scope.', $prompt);
            if (is_wp_error($res)) {
                $error = $res->get_error_message();
            } else {
                $blocks_text = (string)($res['content'] ?? '');
            }
        }

        if (!$error) {
            $blocks = ai_pr_parse_blocks($blocks_text);
            if (!$blocks) $error = 'No blocks recognized. Expect FILE/APPEND/DELETE/RENAME sections.';
        }

        if ($error) {
            echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
            return;
        }

        // Behavior:
        // - Preview: force dry-run ON
        // - Apply: honor checkbox; but writes only when dry-run is OFF
        $dry = $do_preview ? true : (bool)$dry_run;

        list($snapId, $snapDir) = ai_pr_start_snapshot($label ?: ($do_preview ? 'preview' : 'apply-patch'));

        foreach ($blocks as $b) {
            $type    = $b['type'];
            $path    = $b['path'];
            $pathTo  = $b['path_to'];
            $content = $b['content'];

            $abs = ai_pr_resolve_target($path, $allow_core);
            if (!$abs) {
                $preview .= '<div class="notice notice-warning"><p>Skip (outside allowed roots): <code>'.esc_html($path).'</code></p></div>';
                continue;
            }
            if ($type !== 'DELETE' && $type !== 'RENAME' && !ai_pr_is_ext_ok($abs)) {
                $preview .= '<div class="notice notice-warning"><p>Skip (extension policy): <code>'.esc_html($path).'</code></p></div>';
                continue;
            }

            $before = file_exists($abs) ? file_get_contents($abs) : '';
            ai_pr_snapshot_add_file($snapDir, $abs, $before);

            if ($type === 'FILE') {
                $after = $content;
                $preview .= ai_pr_text_diff($before, $after, "FILE → $path");
                if (!$dry) ai_pr_write_file($abs, $after);

            } elseif ($type === 'APPEND') {
                $after = $token_name !== '' ? ai_pr_apply_token_region($before, $token_name, $content)
                                            : (rtrim($before) . "\n\n" . rtrim($content) . "\n");
                $preview .= ai_pr_text_diff($before, $after, "APPEND → $path");
                if (!$dry) ai_pr_write_file($abs, $after);

            } elseif ($type === 'DELETE') {
                $preview .= ai_pr_text_diff($before, '', "DELETE → $path");
                if (!$dry) ai_pr_delete_file($abs);

            } elseif ($type === 'RENAME') {
                $absTo = ai_pr_resolve_target($pathTo, $allow_core);
                if (!$absTo) {
                    $preview .= '<div class="notice notice-error"><p>Skip RENAME (target outside allowed roots): <code>'.esc_html($pathTo).'</code></p></div>';
                    continue;
                }
                $preview .= '<div class="notice notice-info"><p>RENAME: <code>'.esc_html($path).'</code> → <code>'.esc_html($pathTo).'</code></p></div>';
                if (!$dry) ai_pr_rename_file($abs, $absTo);
                // Record destination snapshot entry post-op visibility
                $afterContent = file_exists($absTo) ? file_get_contents($absTo) : '';
                ai_pr_snapshot_add_file($snapDir, $absTo, $afterContent);
            }
        }

        ai_pr_finalize_snapshot($snapDir);
        $note = $dry ? '(dry-run only, no files written)' : '(changes applied)';
        echo '<div class="notice notice-success"><p>Snapshot: <code>'.esc_html($snapId).'</code> '.$note.'</p></div>';
        echo '<div class="card"><div class="inside">'.$preview.'</div></div>';
        ai_pr_log($do_preview ? 'preview' : 'apply_patch', ['snapshot' => $snapId, 'dry' => $dry, 'label' => $label]);
    }

    // Form UI
    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    echo '<h2>Apply Patch</h2>';
    echo '<p class="description">Paste standardized blocks (FILE/APPEND/DELETE/RENAME), or switch to AI mode to generate them.</p>';

    $mode_source = isset($_POST['source_mode']) ? sanitize_text_field($_POST['source_mode']) : 'blocks';
    echo '<p><label><input type="radio" name="source_mode" value="blocks" '.checked($mode_source==='blocks', true, false).'> Provide Blocks</label> ';
    echo '<label><input type="radio" name="source_mode" value="ai" '.checked($mode_source==='ai', true, false).'> Use AI (requires <code>openai_wp_chat()</code>)</label></p>';

    $label_val = isset($_POST['label']) ? sanitize_text_field($_POST['label']) : '';
    echo '<p><label>Label (for snapshot): <input type="text" name="label" class="regular-text" value="'.esc_attr($label_val).'"></label></p>';

    $blocks_val = isset($_POST['blocks']) ? wp_unslash($_POST['blocks']) : '';
    echo '<textarea name="blocks" rows="10" style="width:100%;" placeholder="=== FILE: wp-content/themes/child/functions.php ===
<?php
// new content
?>
=== END FILE ===

=== APPEND: wp-content/themes/child/style.css ===
/* appended block */
=== END APPEND ===
">'.esc_textarea($blocks_val).'</textarea>';

    $task_val = isset($_POST['task']) ? wp_unslash($_POST['task']) : '';
    echo '<p><label>AI Task (if AI mode):</label><br>';
    echo '<textarea name="task" rows="6" style="width:100%;">'.esc_textarea($task_val).'</textarea></p>';

    $token_val = isset($_POST['token']) ? sanitize_key($_POST['token']) : '';
    echo '<p><label>Token name (optional, for APPEND regions): <input type="text" name="token" class="regular-text" value="'.esc_attr($token_val).'" placeholder="sandbox"></label></p>';

    $dry_val = !empty($_POST['dry_run']);
    $allow_core_val = !empty($_POST['allow_core']);
    echo '<p><label><input id="ai_pr_dry_run" type="checkbox" name="dry_run" '.checked($dry_val, true, false).'> Dry-run (diffs only)</label><br>';
    echo '<label><input type="checkbox" name="allow_core" '.checked($allow_core_val, true, false).'> Allow core-sensitive roots (e.g., <code>ABSPATH</code>)</label></p>';

    // Buttons: Preview forces dry-run ON via JS and server-side; Apply writes only when dry-run is OFF
    echo '<p>';
    echo '<button class="button" name="do_preview" value="1" id="ai_pr_preview_btn">Generate Preview</button> ';
    echo '<button class="button button-primary" name="do_apply" value="1" id="ai_pr_apply_btn">Apply Changes</button>';
    echo '</p>';

    echo '</form>';
}

/* ===============================================
 * UI: Search & Replace (regex optional) in roots
 * =============================================== */
function ai_pr_ui_search_replace($nonce_action) {
    $pattern     = isset($_POST['pattern']) ? wp_unslash($_POST['pattern']) : '';
    $replacement = isset($_POST['replacement']) ? wp_unslash($_POST['replacement']) : '';
    $regex       = !empty($_POST['regex']);
    $glob        = isset($_POST['glob']) ? sanitize_text_field($_POST['glob']) : '*.php,*.js,*.css,*.json,*.html,*.txt';
    $root_key    = isset($_POST['root_key']) ? sanitize_text_field($_POST['root_key']) : 'wp-content';
    $dry         = !empty($_POST['dry_run']);
    $allow_core  = !empty($_POST['allow_core']);
    $label       = 'search-replace';

    $roots = ai_pr_allowed_roots();
    $root_path = isset($roots[$root_key]) ? $roots[$root_key]['path'] : WP_CONTENT_DIR;

    if (!empty($_POST['do_sr']) && check_admin_referer($nonce_action)) {
        list($snapId, $snapDir) = ai_pr_start_snapshot($label);
        $patterns = array_map('trim', explode(',', $glob));
        $files = ai_pr_glob_recursive($root_path, $patterns, $allow_core);
        $applied = 0;
        $preview = '';

        foreach ($files as $abs) {
            if (!ai_pr_is_ext_ok($abs)) continue;
            $before = file_get_contents($abs);
            $after = $before;
            if ($regex) {
                $after = @preg_replace($pattern, $replacement, $before);
                if ($after === null) {
                    $preview .= '<div class="notice notice-error"><p>Regex error on: <code>'.esc_html($abs).'</code></p></div>';
                    continue;
                }
            } else {
                $after = str_replace($pattern, $replacement, $before);
            }
            if ($after !== $before) {
                ai_pr_snapshot_add_file($snapDir, $abs, $before);
                $preview .= ai_pr_text_diff($before, $after, 'S/R → ' . esc_html(wp_make_link_relative($abs)));
                if (!$dry) ai_pr_write_file($abs, $after);
                $applied++;
            }
        }
        ai_pr_finalize_snapshot($snapDir);
        echo '<div class="notice notice-success"><p>Snapshot: <code>'.esc_html($snapId).'</code> — Modified files: '.intval($applied).' '.($dry ? '(dry-run)' : '').'</p></div>';
        echo '<div class="card"><div class="inside">'.$preview.'</div></div>';
        ai_pr_log('search_replace', ['snapshot' => $snapId, 'dry' => $dry, 'pattern' => $pattern, 'regex' => $regex]);
    }

    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    echo '<h2>Search & Replace</h2>';
    echo '<p><label>Root: <select name="root_key">';
    foreach ($roots as $key => $meta) {
        echo '<option value="'.esc_attr($key).'" '.selected($root_key===$key, true, false).'>'.esc_html($key.' — '.$meta['path']).'</option>';
    }
    echo '</select></label></p>';
    echo '<p><label>Glob patterns (comma-separated): <input type="text" name="glob" class="regular-text" value="'.esc_attr($glob).'"></label></p>';
    echo '<p><label>Find pattern: <input type="text" name="pattern" class="regular-text" value="'.esc_attr($pattern).'"></label></p>';
    echo '<p><label>Replacement: <input type="text" name="replacement" class="regular-text" value="'.esc_attr($replacement).'"></label></p>';
    echo '<p><label><input type="checkbox" name="regex" '.checked(!empty($_POST['regex']), true, false).'> Treat pattern as PCRE (preg_replace)</label></p>';
    echo '<p><label><input type="checkbox" name="dry_run" '.checked(!empty($_POST['dry_run']), true, false).'> Dry-run</label> ';
    echo '<label><input type="checkbox" name="allow_core" '.checked(!empty($_POST['allow_core']), true, false).'> Allow core-sensitive roots</label></p>';
    echo '<p><button class="button button-primary" name="do_sr" value="1">Preview/Apply</button></p>';
    echo '</form>';
}

/* ===================
 * UI: Revert Snapshot
 * =================== */
function ai_pr_ui_revert($nonce_action) {
    $snapshots = ai_pr_list_snapshots(100);
    $chosen = isset($_POST['snapshot_id']) ? sanitize_text_field($_POST['snapshot_id']) : '';
    $selected_files = isset($_POST['files']) && is_array($_POST['files']) ? array_map('wp_unslash', $_POST['files']) : [];
    $dry = !empty($_POST['dry_run']);
    $allow_core = !empty($_POST['allow_core']);

    if (!empty($_POST['do_revert']) && check_admin_referer($nonce_action)) {
        $snap = ai_pr_load_snapshot($chosen);
        if (!$snap) {
            echo '<div class="notice notice-error"><p>Snapshot not found.</p></div>';
        } else {
            list($newId, $newDir) = ai_pr_start_snapshot('revert-of-'.$chosen);
            $preview = '';
            $files = $snap['files'];
            $applyList = empty($selected_files) ? array_keys($files) : $selected_files;

            foreach ($applyList as $abs) {
                $absAllowed = ai_pr_resolve_target($abs, $allow_core);
                if (!$absAllowed) {
                    $preview .= '<div class="notice notice-warning"><p>Skip (outside allowed roots): <code>'.esc_html($abs).'</code></p></div>';
                    continue;
                }
                $meta = $files[$abs];
                $before = file_exists($abs) ? file_get_contents($abs) : '';
                ai_pr_snapshot_add_file($newDir, $abs, $before); // record current state pre-revert

                $blobFile = $snap['_dir'] . '/files/' . md5($abs) . '.before';
                $after = $meta['exists_before'] ? (file_exists($blobFile) ? file_get_contents($blobFile) : '') : '';

                $preview .= ai_pr_text_diff($before, $after, 'REVERT → '.esc_html(wp_make_link_relative($abs)));
                if (!$dry) {
                    if ($meta['exists_before']) {
                        ai_pr_write_file($abs, $after);
                    } else {
                        ai_pr_delete_file($abs);
                    }
                }
            }

            ai_pr_finalize_snapshot($newDir);
            echo '<div class="notice notice-success"><p>Revert snapshot: <code>'.esc_html($chosen).'</code> → Created new snapshot <code>'.esc_html($newId).'</code> '.($dry ? '(dry-run)' : '(applied)').'</p></div>';
            echo '<div class="card"><div class="inside">'.$preview.'</div></div>';
            ai_pr_log('revert', ['reverted' => $chosen, 'snapshot' => $newId, 'dry' => $dry]);
        }
    }

    echo '<form method="post">';
    wp_nonce_field($nonce_action);
    echo '<h2>Revert Snapshot</h2>';
    echo '<p><label>Select snapshot: <select name="snapshot_id">';
    foreach ($snapshots as $s) {
        $label = $s['id'] . ' — ' . ($s['label'] ?: 'no label') . ' — ' . $s['created'];
        echo '<option value="'.esc_attr($s['id']).'" '.selected($chosen===$s['id'], true, false).'>'.esc_html($label).'</option>';
    }
    echo '</select></label></p>';

    if ($chosen) {
        $snap = ai_pr_load_snapshot($chosen);
        if ($snap) {
            echo '<p><strong>Files in snapshot</strong></p><div style="max-height:220px;overflow:auto;border:1px solid #ddd;padding:8px;">';
            foreach ($snap['files'] as $abs => $meta) {
                echo '<label style="display:block;"><input type="checkbox" name="files[]" value="'.esc_attr($abs).'"> '.esc_html($abs).'</label>';
            }
            echo '</div>';
        }
    }

    echo '<p><label><input type="checkbox" name="dry_run" '.checked(!empty($_POST['dry_run']), true, false).'> Dry-run</label> ';
    echo '<label><input type="checkbox" name="allow_core" '.checked(!empty($_POST['allow_core']), true, false).'> Allow core-sensitive roots</label></p>';
    echo '<p><button class="button button-primary" name="do_revert" value="1">Preview/Apply Revert</button></p>';
    echo '</form>';
}

/* ======================
 * UI: Snapshot browser
 * ====================== */
function ai_pr_ui_snapshots() {
    $snaps = ai_pr_list_snapshots(200);
    echo '<h2>Snapshots</h2>';
    if (!$snaps) {
        echo '<p>No snapshots yet.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Created</th><th>Label</th><th>Files</th></tr></thead><tbody>';
    foreach ($snaps as $s) {
        echo '<tr>';
        echo '<td><code>'.esc_html($s['id']).'</code></td>';
        echo '<td>'.esc_html($s['created'] ?? '').'</td>';
        echo '<td>'.esc_html($s['label'] ?? '').'</td>';
        echo '<td>'.intval(is_countable($s['files']) ? count($s['files']) : 0).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/* =====
 * About
 * ===== */
function ai_pr_ui_about() {
    echo '<h2>About</h2>';
    echo '<p>AI Patch Runner v'.esc_html(AI_PR_VERSION).'. This tool applies structured patches with diffs, snapshots, and reverts. Use responsibly.</p>';
    echo '<p><strong>Tips:</strong> Prefer editing within <code>wp-content</code>. Enable core access only when necessary. Keep dry-run on until diffs look right; use <em>Apply Changes</em> only when ready.</p>';
}

/* =====================
 * Helpers: recursive fs
 * ===================== */
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
                $dry ? WP_CLI::line(strip_tags(ai_pr_text_diff($before, $after, "FILE → $path"))) : ai_pr_write_file($abs, $after);

            } elseif ($type === 'APPEND') {
                $after = $token !== '' ? ai_pr_apply_token_region($before, $token, $content) : (rtrim($before) . "\n\n" . rtrim($content) . "\n");
                $dry ? WP_CLI::line(strip_tags(ai_pr_text_diff($before, $after, "APPEND → $path"))) : ai_pr_write_file($abs, $after);

            } elseif ($type === 'DELETE') {
                $dry ? WP_CLI::line(strip_tags(ai_pr_text_diff($before, '', "DELETE → $path"))) : ai_pr_delete_file($abs);

            } elseif ($type === 'RENAME') {
                $absTo = ai_pr_resolve_target($pathTo, $allow_core);
                if (!$absTo) { WP_CLI::warning("Skip RENAME (target outside allowed): {$b['path_to']}"); continue; }
                if ($dry) {
                    WP_CLI::line("RENAME (dry): $path → {$b['path_to']}");
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
        if (!$id) WP_CLI::error('Usage: wp ai-pr revert <snapshot-id> [--file=<abs> ...] [--dry] [--allow-core]');
        $files = (array)($assoc['file'] ?? []);
        $dry = !empty($assoc['dry']);
        $allow_core = !empty($assoc['allow-core']);
        $snap = ai_pr_load_snapshot($id);
        if (!$snap) WP_CLI::error('Snapshot not found.');
        list($newId, $newDir) = ai_pr_start_snapshot('revert-of-'.$id);

        $applyList = $files ? $files : array_keys($snap['files']);
        foreach ($applyList as $abs) {
            $absAllowed = ai_pr_resolve_target($abs, $allow_core);
            if (!$absAllowed) { WP_CLI::warning("Skip (outside allowed): $abs"); continue; }
            $before = file_exists($abs) ? file_get_contents($abs) : '';
            ai_pr_snapshot_add_file($newDir, $abs, $before);

            $meta = $snap['files'][$abs] ?? null;
            if (!$meta) { WP_CLI::warning("Not in snapshot: $abs"); continue; }
            $blob = $snap['_dir'] . '/files/' . md5($abs) . '.before';
            $after = $meta['exists_before'] ? (file_exists($blob) ? file_get_contents($blob) : '') : '';

            if ($dry) {
                WP_CLI::line(strip_tags(ai_pr_text_diff($before, $after, "REVERT → $abs")));
            } else {
                if ($meta['exists_before']) {
                    ai_pr_write_file($abs, $after);
                } else {
                    ai_pr_delete_file($abs);
                }
            }
        }
        ai_pr_finalize_snapshot($newDir);
        WP_CLI::success("Revert snapshot created: $newId " . ($dry ? '(dry-run)' : '(applied)'));
    });

    WP_CLI::add_command('ai-pr search-replace', function($args, $assoc){
        $root   = $assoc['root'] ?? WP_CONTENT_DIR;
        $glob   = $assoc['glob'] ?? '*.php,*.js,*.css,*.json,*.html,*.txt';
        $pattern= $assoc['pattern'] ?? null;
        $repl   = $assoc['replacement'] ?? '';
        $regex  = !empty($assoc['regex']);
        $dry    = !empty($assoc['dry']);
        $allow_core = !empty($assoc['allow-core']);

        if ($pattern === null) WP_CLI::error('--pattern is required');

        list($snapId, $snapDir) = ai_pr_start_snapshot('search-replace');
        $patterns = array_map('trim', explode(',', $glob));
        $files = ai_pr_glob_recursive($root, $patterns, $allow_core);
        $count = 0;
        foreach ($files as $abs) {
            if (!ai_pr_is_ext_ok($abs)) continue;
            $before = file_get_contents($abs);
            $after = $regex ? (preg_replace($pattern, $repl, $before) ?? $before) : str_replace($pattern, $repl, $before);
            if ($after !== $before) {
                ai_pr_snapshot_add_file($snapDir, $abs, $before);
                $dry ? WP_CLI::line(strip_tags(ai_pr_text_diff($before, $after, "S/R → $abs"))) : ai_pr_write_file($abs, $after);
                $count++;
            }
        }
        ai_pr_finalize_snapshot($snapDir);
        WP_CLI::success("Snapshot: $snapId, modified: $count " . ($dry ? '(dry-run)' : '(applied)'));
    });
}

/* ==========================
 * Notice: AI bridge missing
 * ========================== */
add_action('admin_notices', function(){
    if (!current_user_can('manage_options')) return;
    if (!function_exists('openai_wp_chat')) {
        echo '<div class="notice notice-warning"><p><strong>AI Patch Runner:</strong> <code>openai_wp_chat()</code> not detected. AI mode disabled; manual blocks and tools still work.</p></div>';
    }
});

/* END v2.1.0 */
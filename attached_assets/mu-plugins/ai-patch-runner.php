<?php
/**
 * Plugin Name: AI Patch Runner (MU)
 * Description: Generate and apply guarded file edits to the child theme using OpenAI. Supports APPEND (safe) and REPLACE. Admin-only, previews before write, backs up originals.
 * Version: 1.2.0
 */

if (!defined('ABSPATH')) { exit; }

// >>> CHANGE THIS if your child theme folder is different.
$childThemeDir = 'triuu'; // e.g., 'ken-blank-child'

add_action('admin_menu', function () use ($childThemeDir) {
    add_management_page(
        'AI Patch Runner',
        'AI Patch Runner',
        'manage_options',
        'ai-patch-runner',
        function () use ($childThemeDir) { ai_patch_runner_screen($childThemeDir); }
    );
});

function ai_patch_runner_screen($childThemeDir) {
    if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

    if (!function_exists('openai_wp_chat')) {
        echo '<div class="notice notice-error"><p>Missing dependency: <code>openai_wp_chat()</code> not found. Ensure the OpenAI WP Service MU plugin is present.</p></div>';
        return;
    }

    $base = WP_CONTENT_DIR . '/themes/' . $childThemeDir;
    $baseReal = realpath($base);
    $baseUrl = content_url('themes/' . $childThemeDir);

    if (!$baseReal || !is_dir($baseReal)) {
        echo '<div class="notice notice-error"><p>Child theme folder not found: ' . esc_html($base) . '</p></div>';
        return;
    }

    $notice = ''; $error = ''; $blocks = array(); $mode = 'APPEND';

    // Helpers
    $ext_ok = function($p) { return (bool)preg_match('/\.(css|php|js)$/i', $p); };
    $inside_base = function($rel) use ($baseReal) {
        $target = realpath($baseReal . '/' . ltrim($rel, '/'));
        return $target && str_starts_with($target, $baseReal);
    };
    $parse_blocks = function($text) {
        // Supports:
        // === FILE: path === ... === END FILE ===
        // === APPEND: path === ... === END APPEND ===
        $out = array('FILE'=>array(), 'APPEND'=>array());
        $filePat   = '/^===\s*FILE:\s*(.+?)\s*===\R(.*?)\R^===\s*END FILE\s*===\s*$/ms';
        $appendPat = '/^===\s*APPEND:\s*(.+?)\s*===\R(.*?)\R^===\s*END APPEND\s*===\s*$/ms';
        if (preg_match_all($filePat, $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) $out['FILE'][trim($hit[1])] = $hit[2];
        }
        if (preg_match_all($appendPat, $text, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $hit) $out['APPEND'][trim($hit[1])] = $hit[2];
        }
        return $out;
    };

    // Mode handling
    if (isset($_POST['ai_mode']) && in_array($_POST['ai_mode'], array('APPEND','REPLACE'), true)) {
        $mode = $_POST['ai_mode'];
    }

    // Generate
    if (isset($_POST['ai_generate']) && check_admin_referer('ai_patch_runner')) {
        $task = trim(wp_unslash($_POST['task'] ?? ''));
        if ($task === '') {
            $error = 'Describe the change you want.';
        } else {
            $sys = "You are a precise code editor for a WordPress child theme at wp-content/themes/{$childThemeDir}.
Return ONLY blocks in one of these formats:

For REPLACE (entire file content):
=== FILE: RELATIVE/PATH ===
<ENTIRE FILE CONTENT>
=== END FILE ===

For APPEND (append snippet to existing file; do not overwrite):
=== APPEND: RELATIVE/PATH ===
<CODE TO APPEND (wrap in proper tags if PHP)>
=== END APPEND ===

Rules:
- Work ONLY under wp-content/themes/{$childThemeDir}.
- Allowed extensions: .css, .php, .js
- Prefer APPEND for small, safe changes (e.g., inline CSS via functions.php).
- NEVER create new files. If a file doesn’t exist, do not propose it.
- Keep changes minimal and directly satisfy the request.";

            $user = "MODE: {$mode}\nREQUEST:\n{$task}";

            $resp = openai_wp_chat(
                array(
                    array('role'=>'system','content'=>$sys),
                    array('role'=>'user','content'=>$user)
                ),
                array('model'=>'gpt-4o-mini','max_tokens'=>2200,'temperature'=>0.1,'cache_ttl'=>0)
            );

            if (is_wp_error($resp)) {
                $error = 'OpenAI error: ' . $resp->get_error_message();
            } else {
                $parsed = $parse_blocks((string)$resp['content']);
                $blocks = $parsed;
                if ($mode === 'APPEND'  && empty($blocks['APPEND'])) $error = 'No APPEND blocks returned.';
                if ($mode === 'REPLACE' && empty($blocks['FILE']))   $error = 'No FILE blocks returned.';
                if (!$error) {
                    $notice = 'Preview generated. Review and click "Apply" to write files.';
                    set_transient('ai_patch_runner_last', array('blocks'=>$blocks,'mode'=>$mode), 10 * MINUTE_IN_SECONDS);
                }
            }
        }
    }

    // Apply
    if (isset($_POST['ai_apply']) && check_admin_referer('ai_patch_runner')) {
        $saved = get_transient('ai_patch_runner_last');
        $blocks = is_array($saved) ? ($saved['blocks'] ?? array()) : array();
        $mode   = is_array($saved) ? ($saved['mode']   ?? 'APPEND') : 'APPEND';

        if (!$blocks) {
            $error = 'Nothing to apply. Generate first.';
        } else {
            $backupDir = WP_CONTENT_DIR . '/ai-backups/' . gmdate('Ymd-His');
            wp_mkdir_p($backupDir);
            $wrote = 0; $bad = array();

            if ($mode === 'REPLACE') {
                foreach ($blocks['FILE'] as $rel => $content) {
                    $rel = ltrim($rel, '/');
                    if (!$ext_ok($rel) || !$inside_base($rel)) { $bad[] = $rel; continue; }
                    $dst = $baseReal . '/' . $rel;
                    if (!file_exists($dst)) { $bad[] = $rel; continue; }
                    @copy($dst, $backupDir . '/' . str_replace('/', '__', $rel));
                    $ok = file_put_contents($dst, $content);
                    if ($ok === false) { $bad[] = $rel; } else { $wrote++; }
                }
            } else { // APPEND
                foreach ($blocks['APPEND'] as $rel => $snippet) {
                    $rel = ltrim($rel, '/');
                    if (!$ext_ok($rel) || !$inside_base($rel)) { $bad[] = $rel; continue; }
                    $dst = $baseReal . '/' . $rel;
                    if (!file_exists($dst)) { $bad[] = $rel; continue; }
                    @copy($dst, $backupDir . '/' . str_replace('/', '__', $rel));
                    $append = "\n\n/* AI Patch Runner APPEND ".gmdate('c')." */\n" . $snippet . "\n";
                    $ok = file_put_contents($dst, $append, FILE_APPEND);
                    if ($ok === false) { $bad[] = $rel; } else { $wrote++; }
                }
            }

            if ($wrote > 0) { $notice = "Applied {$wrote} change(s). Backups at: wp-content/ai-backups/" . basename($backupDir); }
            if ($bad)       { $error  = 'Skipped/failed: ' . esc_html(implode(', ', $bad)); }

            delete_transient('ai_patch_runner_last');
        }
    }

    // Reload preview after generate
    $saved = get_transient('ai_patch_runner_last');
    if ($saved && empty($blocks)) { $blocks = $saved['blocks'] ?? array(); $mode = $saved['mode'] ?? $mode; }

    ?>
    <div class="wrap">
      <h1>AI Patch Runner</h1>
      <?php if ($notice): ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
      <?php if ($error):  ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('ai_patch_runner'); ?>
        <p><strong>Mode:</strong>
          <label style="margin-right:12px"><input type="radio" name="ai_mode" value="APPEND" <?php checked($mode,'APPEND'); ?>> Append (safe)</label>
          <label><input type="radio" name="ai_mode" value="REPLACE" <?php checked($mode,'REPLACE'); ?>> Replace (whole file)</label>
        </p>
        <p><label for="task"><strong>Describe the change</strong> (e.g., “Append inline CSS via functions.php to set H1 color to #333.”)</label></p>
        <textarea id="task" name="task" rows="6" class="large-text" placeholder="Describe the exact change you want..."><?php echo isset($_POST['task']) ? esc_textarea(wp_unslash($_POST['task'])) : ''; ?></textarea>
        <p>
          <button class="button button-primary" name="ai_generate" value="1">Generate Preview</button>
          <?php if ($blocks) : ?>
            <button class="button" name="ai_apply" value="1">Apply Changes</button>
          <?php endif; ?>
        </p>
      </form>

      <?php if ($blocks): ?>
        <h2>Preview</h2>
        <?php if ($mode === 'REPLACE' && !empty($blocks['FILE'])): ?>
          <?php foreach ($blocks['FILE'] as $rel => $content): ?>
            <h3><code><?php echo esc_html($rel); ?></code> (REPLACE)</h3>
            <p><a href="<?php echo esc_url($baseUrl . '/' . ltrim($rel,'/')); ?>" target="_blank" rel="noopener">View current file</a></p>
            <pre style="background:#111;color:#0f0;max-height:420px;overflow:auto;padding:12px;"><?php echo esc_html($content); ?></pre>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($mode === 'APPEND' && !empty($blocks['APPEND'])): ?>
          <?php foreach ($blocks['APPEND'] as $rel => $snippet): ?>
            <h3><code><?php echo esc_html($rel); ?></code> (APPEND)</h3>
            <p><a href="<?php echo esc_url($baseUrl . '/' . ltrim($rel,'/')); ?>" target="_blank" rel="noopener">View current file</a></p>
            <pre style="background:#111;color:#0f0;max-height:420px;overflow:auto;padding:12px;"><?php echo esc_html($snippet); ?></pre>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php
}

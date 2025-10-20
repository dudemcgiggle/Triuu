<?php
/**
 * Plugin Name: AI Patch Runner (MU)
 * Description: Generate and apply guarded file edits to the child theme using OpenAI. Preview diffs, back up originals, restrict paths, and support APPEND/REPLACE and tokenized region updates.
 * Version: 1.5.0
 * Author: Ken + Helper
 */

if (!defined('ABSPATH')) { exit; }

/* -----------------------------------------------------------------------------
 * CONFIG
 * -------------------------------------------------------------------------- */

// >>> CHANGE THIS if your child theme folder is different.
$AI_PR_CHILD_THEME_DIR = 'triuu'; // e.g., 'triuu', 'ken-blank-child'

// Allowed relative paths (case-insensitive) inside the child theme. Keep small!
$AI_PR_ALLOWLIST = array(
  'functions.php',
  'style.css',
  // Add more relative paths carefully, e.g. 'assets/js/site.js', 'inc/custom.php'
);

// Allowed file extensions for edits
$AI_PR_EXTS = '/\.(php|js|css)$/i';

// Dependency: openai_wp_chat() must exist (provided by your OpenAI MU service).
if (!function_exists('openai_wp_chat')) {
  // Surface a clear admin notice instead of fatal.
  add_action('admin_notices', function(){
    if (!current_user_can('manage_options')) return;
    echo '<div class="notice notice-error"><p><strong>AI Patch Runner:</strong> missing dependency <code>openai_wp_chat()</code>. Install/enable your OpenAI MU service.</p></div>';
  });
  // We still register the menu so you see the notice.
}

/* -----------------------------------------------------------------------------
 * ADMIN MENU
 * -------------------------------------------------------------------------- */

add_action('admin_menu', function () use ($AI_PR_CHILD_THEME_DIR) {
  add_management_page(
    'AI Patch Runner',
    'AI Patch Runner',
    'manage_options',
    'ai-patch-runner',
    function () use ($AI_PR_CHILD_THEME_DIR) { ai_pr_screen($AI_PR_CHILD_THEME_DIR); }
  );
});

/* -----------------------------------------------------------------------------
 * UTIL: resolve + guard a relative child-theme path
 * -------------------------------------------------------------------------- */

function ai_pr_theme_base(string $childThemeDir): array {
  $base = WP_CONTENT_DIR . '/themes/' . $childThemeDir;
  $real = realpath($base);
  $url  = content_url('themes/' . $childThemeDir);
  return array($base, $real, $url);
}

function ai_pr_inside_child(string $rel, string $baseReal): ?string {
  $rel = ltrim($rel, '/');
  $target = realpath($baseReal . '/' . $rel);
  if (!$target) return null;
  // must be inside baseReal
  if (strpos($target, $baseReal) !== 0) return null;
  return $target;
}

function ai_pr_ext_ok(string $path, string $ext_regex): bool {
  return (bool)preg_match($ext_regex, $path);
}

function ai_pr_is_allowlisted(string $rel, array $allow): bool {
  $relLower = strtolower(ltrim($rel, '/'));
  foreach ($allow as $a) {
    if (strtolower(trim($a)) === $relLower) return true;
  }
  return false;
}

/* -----------------------------------------------------------------------------
 * PARSING: accept FILE and APPEND blocks
 * -------------------------------------------------------------------------- */

function ai_pr_parse_blocks(string $text): array {
  // Supports:
  // === FILE: path === ... === END FILE ===
  // === APPEND: path === ... === END APPEND ===
  $out = array('FILE'=>array(), 'APPEND'=>array());
  $filePat   = '/^===\s*FILE:\s*(.+?)\s*===\R(.*?)\R^===\s*END FILE\s*===\s*$/ms';
  $appendPat = '/^===\s*APPEND:\s*(.+?)\s*===\R(.*?)\R^===\s*END APPEND\s*===\s*$/ms';
  if (preg_match_all($filePat, $text, $m, PREG_SET_ORDER)) {
    foreach ($m as $hit) { $out['FILE'][trim($hit[1])] = $hit[2]; }
  }
  if (preg_match_all($appendPat, $text, $m2, PREG_SET_ORDER)) {
    foreach ($m2 as $hit) { $out['APPEND'][trim($hit[1])] = $hit[2]; }
  }
  return $out;
}

/* -----------------------------------------------------------------------------
 * TOKENIZED REGION UPDATES
 * Only mutate content between <!-- AI:start:name --> and <!-- AI:end:name -->
 * If no tokens are found, return original content unchanged.
 * -------------------------------------------------------------------------- */

function ai_pr_replace_region(string $original, string $name, string $replacement): string {
  $start = '<!-- AI:start:' . $name . ' -->';
  $end   = '<!-- AI:end:' . $name . ' -->';
  $pos1 = strpos($original, $start);
  $pos2 = strpos($original, $end);
  if ($pos1 === false || $pos2 === false || $pos2 < $pos1) {
    return $original; // no tokens -> no change
  }
  $before = substr($original, 0, $pos1 + strlen($start));
  $after  = substr($original, $pos2);
  // Keep the exact token markers; replace inside
  return $before . "\n" . rtrim($replacement) . "\n" . $after;
}

/* -----------------------------------------------------------------------------
 * DIFF PREVIEW (uses wp_text_diff when available; falls back otherwise)
 * -------------------------------------------------------------------------- */

function ai_pr_unified_diff(string $old, string $new, string $title = ''): string {
  if (function_exists('wp_text_diff')) {
    return wp_text_diff($old, $new, array('title'=>$title !== '' ? $title : 'Diff'));
  }
  // Minimal fallback: show both with separators
  $esc = function($s){ return '<pre style="white-space:pre-wrap;background:#111;color:#0f0;padding:12px;overflow:auto;">' . esc_html($s) . '</pre>'; };
  return '<h3>' . esc_html($title ?: 'Preview (fallback)') . '</h3>'
       . '<h4>Before</h4>' . $esc($old)
       . '<h4>After</h4>'  . $esc($new);
}

/* -----------------------------------------------------------------------------
 * OPENAI CALL WRAPPER
 * -------------------------------------------------------------------------- */

function ai_pr_generate_patch_text(string $task, array $context = array()) {
  if (!function_exists('openai_wp_chat')) {
    return new WP_Error('missing_dep', 'openai_wp_chat() not available.');
  }

  $sys = "You are a precise code editor. Respond ONLY with FILE or APPEND blocks.\n"
       . "Use these formats exactly:\n"
       . "=== FILE: relative/path ===\n<entire new file content>\n=== END FILE ===\n\n"
       . "=== APPEND: relative/path ===\n<content to append>\n=== END APPEND ===\n\n"
       . "Rules:\n"
       . "- Target files must be inside the specified child theme folder.\n"
       . "- Prefer APPEND for small tweaks; use FILE only when replacing whole file safely.\n"
       . "- If region tokens exist (<!-- AI:start:name -->...<!-- AI:end:name -->), limit changes to within those tokens.\n"
       . "- Do not include explanations—only blocks.";
  $user = "Task:\n" . $task . "\n\nContext:\n" . wp_json_encode($context);

  $res = openai_wp_chat(array(
    'system'      => $sys,
    'messages'    => array(array('role'=>'user','content'=>$user)),
    'max_tokens'  => 1200,
    'temperature' => 0.2,
  ));
  return $res;
}

/* -----------------------------------------------------------------------------
 * WRITE OPERATIONS: REPLACE and APPEND with backups
 * -------------------------------------------------------------------------- */

function ai_pr_backup_path(string $absTarget): string {
  $uploads = wp_upload_dir();
  $root = trailingslashit($uploads['basedir']) . 'ai-backups/';
  wp_mkdir_p($root);
  $ts = date('Ymd-His');
  // Preserve relative structure from wp-content/themes
  $from_content = strpos($absTarget, WP_CONTENT_DIR) === 0 ? substr($absTarget, strlen(WP_CONTENT_DIR)+1) : basename($absTarget);
  $dest = $root . $ts . '/' . $from_content;
  wp_mkdir_p(dirname($dest));
  return $dest;
}

function ai_pr_apply_replace(string $abs, string $new, bool $do_backup): array {
  $before = file_exists($abs) ? file_get_contents($abs) : '';
  if ($do_backup && file_exists($abs)) {
    $bak = ai_pr_backup_path($abs);
    copy($abs, $bak);
  }
  // Write atomically
  $tmp = $abs . '.tmp.' . get_current_user_id();
  file_put_contents($tmp, $new);
  @chmod($tmp, fileperms($abs) ?: 0644);
  rename($tmp, $abs);
  clearstatcache();
  return array('before'=>$before, 'after'=>$new);
}

function ai_pr_apply_append(string $abs, string $append, bool $do_backup, ?string $tokenName = null): array {
  $before = file_exists($abs) ? file_get_contents($abs) : '';
  $after  = $before;

  if ($tokenName !== null) {
    $after = ai_pr_replace_region($before, $tokenName, $append);
    if ($after === $before) {
      // If tokens not found, append to end (explicit fallback)
      $after = rtrim($before) . "\n\n" . $append . "\n";
    }
  } else {
    $after = rtrim($before) . "\n\n" . $append . "\n";
  }

  if ($do_backup && file_exists($abs)) {
    $bak = ai_pr_backup_path($abs);
    copy($abs, $bak);
  }
  $tmp = $abs . '.tmp.' . get_current_user_id();
  file_put_contents($tmp, $after);
  @chmod($tmp, fileperms($abs) ?: 0644);
  rename($tmp, $abs);
  clearstatcache();
  return array('before'=>$before, 'after'=>$after);
}

/* -----------------------------------------------------------------------------
 * ADMIN SCREEN
 * -------------------------------------------------------------------------- */

function ai_pr_screen(string $childThemeDir) {
  if (!current_user_can('manage_options')) { wp_die('Forbidden'); }
  list($base, $baseReal, $baseUrl) = ai_pr_theme_base($childThemeDir);

  if (!$baseReal || !is_dir($baseReal)) {
    echo '<div class="notice notice-error"><p>Child theme folder not found: ' . esc_html($base) . '</p></div>';
    return;
  }

  $notice = ''; $error = ''; $html_diff = '';
  $mode = isset($_POST['ai_mode']) && in_array($_POST['ai_mode'], array('APPEND','REPLACE'), true) ? $_POST['ai_mode'] : 'APPEND';
  $dry  = !empty($_POST['ai_dry']);
  $backup = !empty($_POST['ai_backup']);
  $token_name = isset($_POST['ai_token']) ? trim(strval($_POST['ai_token'])) : '';

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('ai_patch_runner')) {
    $task = trim(wp_unslash($_POST['task'] ?? ''));
    $allowlist_input = isset($_POST['ai_allowlist']) ? trim(strval($_POST['ai_allowlist'])) : '';
    $allowlist = $allowlist_input !== '' ? array_values(array_filter(array_map('trim', explode(',', $allowlist_input)))) : $GLOBALS['AI_PR_ALLOWLIST'];

    if ($task === '') {
      $error = 'Describe the change you want.';
    } elseif (!function_exists('openai_wp_chat')) {
      $error = 'Missing openai_wp_chat() dependency.';
    } else {
      // Provide context to the model
      $context = array(
        'theme_base' => $base,
        'allowlist'  => $allowlist,
        'mode'       => $mode,
        'token_hint' => $token_name !== '' ? $token_name : null,
      );
      $ai = ai_pr_generate_patch_text($task, $context);
      if (is_wp_error($ai)) {
        $error = $ai->get_error_message();
      } else {
        $text = trim((string)($ai['content'] ?? ''));
        $blocks = ai_pr_parse_blocks($text);

        if (empty($blocks['FILE']) && empty($blocks['APPEND'])) {
          $error = 'No FILE/APPEND blocks found in model output.';
        } else {
          // Iterate and validate
          $previews = '';
          foreach (array('FILE','APPEND') as $kind) {
            foreach ($blocks[$kind] as $rel => $payload) {
              if (!ai_pr_is_allowlisted($rel, $allowlist)) {
                $previews .= '<div class="notice notice-warning"><p>Skipped non-allowlisted path: <code>' . esc_html($rel) . '</code></p></div>';
                continue;
              }
              if (!ai_pr_ext_ok($rel, $GLOBALS['AI_PR_EXTS'])) {
                $previews .= '<div class="notice notice-warning"><p>Skipped due to extension policy: <code>' . esc_html($rel) . '</code></p></div>';
                continue;
              }
              $abs = ai_pr_inside_child($rel, $baseReal);
              if (!$abs) {
                $previews .= '<div class="notice notice-error"><p>Bad path (outside child theme): <code>' . esc_html($rel) . '</code></p></div>';
                continue;
              }
              $before = file_exists($abs) ? file_get_contents($abs) : '';
              $after  = $before;

              if ($kind === 'FILE' && $mode === 'REPLACE') {
                $after = $payload;
              } elseif ($kind === 'APPEND' && $mode === 'APPEND') {
                $after = ($token_name !== '') ? ai_pr_replace_region($before, $token_name, $payload) : (rtrim($before) . "\n\n" . $payload . "\n");
              } else {
                // Kind/mode mismatch; warn and skip
                $previews .= '<div class="notice notice-warning"><p>Skipping <code>'.esc_html($kind).'</code> for <code>'.esc_html($rel).'</code> because UI mode is <code>'.esc_html($mode).'</code>.</p></div>';
                continue;
              }

              $previews .= ai_pr_unified_diff($before, $after, strtoupper($kind).' → '.$rel);
              if (!$dry) {
                if ($kind === 'FILE' && $mode === 'REPLACE') {
                  ai_pr_apply_replace($abs, $after, $backup);
                } elseif ($kind === 'APPEND' && $mode === 'APPEND') {
                  // write happens in apply_append (to ensure backup + perms)
                  ai_pr_apply_append($abs, $payload, $backup, $token_name !== '' ? $token_name : null);
                }
              }
            }
          }
          $html_diff = $previews;
          $notice = $dry ? 'DRY RUN complete. No files written.' : 'Patch applied.';
        }
      }
    }
  }

  // Render
  ?>
  <div class="wrap">
    <h1>AI Patch Runner</h1>
    <?php if ($notice): ?><div class="notice notice-success"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
    <?php if ($error):  ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

    <form method="post">
      <?php wp_nonce_field('ai_patch_runner'); ?>

      <table class="form-table" role="presentation">
        <tr>
          <th scope="row">Mode</th>
          <td>
            <label><input type="radio" name="ai_mode" value="APPEND" <?php checked(empty($_POST) || $mode==='APPEND'); ?>> APPEND (safe)</label><br>
            <label><input type="radio" name="ai_mode" value="REPLACE" <?php checked($mode==='REPLACE'); ?>> REPLACE (full file)</label>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="task">Task</label></th>
          <td><textarea name="task" id="task" rows="7" class="large-text" placeholder="Describe the change you want. Mention file(s) and any tokenized regions (e.g., hero) if relevant."></textarea></td>
        </tr>
        <tr>
          <th scope="row"><label for="ai_allowlist">Allowlist (relative paths)</label></th>
          <td>
            <input name="ai_allowlist" id="ai_allowlist" type="text" class="regular-text" placeholder="functions.php, style.css">
            <p class="description">Edits are restricted to these child-theme paths. Leave blank to use plugin defaults.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="ai_token">Token name (optional)</label></th>
          <td>
            <input name="ai_token" id="ai_token" type="text" class="regular-text" placeholder="hero">
            <p class="description">When set, APPEND edits are limited to <!-- AI:start:NAME --> ... <!-- AI:end:NAME --> in target files.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Flags</th>
          <td>
            <label><input type="checkbox" name="ai_dry" <?php checked(empty($_POST)); ?>> Dry run (preview only)</label><br>
            <label><input type="checkbox" name="ai_backup" checked> Back up originals before writing</label>
          </td>
        </tr>
      </table>

      <?php submit_button('Generate Patch'); ?>
    </form>

    <?php if ($html_diff) { echo '<hr><h2>Preview</h2>' . $html_diff; } ?>
  </div>
  <?php
}

/* -----------------------------------------------------------------------------
 * WP-CLI COMMAND
 * -------------------------------------------------------------------------- */

if (defined('WP_CLI') && WP_CLI) {
  WP_CLI::add_command('ai patch', function ($args, $assoc) {
    $mode   = isset($assoc['mode']) && in_array(strtolower($assoc['mode']), array('append','replace'), true) ? strtoupper($assoc['mode']) : 'APPEND';
    $file   = isset($assoc['file']) ? trim(strval($assoc['file'])) : '';
    $task   = isset($assoc['task']) ? trim(strval($assoc['task'])) : '';
    $dry    = isset($assoc['dry-run']);
    $backup = isset($assoc['backup']);
    $token  = isset($assoc['token']) ? trim(strval($assoc['token'])) : '';

    if ($task === '') WP_CLI::error('Provide --task="..."');
    if ($file === '') WP_CLI::error('Provide --file=relative/path');

    global $AI_PR_CHILD_THEME_DIR, $AI_PR_ALLOWLIST, $AI_PR_EXTS;

    list($base, $baseReal, $baseUrl) = ai_pr_theme_base($AI_PR_CHILD_THEME_DIR);
    if (!$baseReal) WP_CLI::error('Child theme not found.');

    // allowlist override for CLI is single file unless user passes --allowlist
    $allowlist = array($file);
    if (!empty($assoc['allowlist'])) {
      $allowlist = array_values(array_filter(array_map('trim', explode(',', strval($assoc['allowlist'])))));
    }

    $ai = ai_pr_generate_patch_text($task, array(
      'theme_base' => $base,
      'allowlist'  => $allowlist,
      'mode'       => $mode,
      'token_hint' => ($token !== '' ? $token : null),
    ));
    if (is_wp_error($ai)) WP_CLI::error($ai->get_error_message());

    $text = trim((string)($ai['content'] ?? ''));
    $blocks = ai_pr_parse_blocks($text);
    if (empty($blocks['FILE']) && empty($blocks['APPEND'])) {
      WP_CLI::error('No FILE/APPEND blocks found.');
    }

    foreach (array('FILE','APPEND') as $kind) {
      foreach ($blocks[$kind] as $rel => $payload) {
        if (!ai_pr_is_allowlisted($rel, $allowlist)) {
          WP_CLI::warning("Skip non-allowlisted: $rel");
          continue;
        }
        if (!ai_pr_ext_ok($rel, $AI_PR_EXTS)) {
          WP_CLI::warning("Skip by extension policy: $rel");
          continue;
        }
        $abs = ai_pr_inside_child($rel, $baseReal);
        if (!$abs) {
          WP_CLI::warning("Skip outside child theme: $rel");
          continue;
        }

        $before = file_exists($abs) ? file_get_contents($abs) : '';
        $after  = $before;

        if ($kind === 'FILE' && $mode === 'REPLACE') {
          $after = $payload;
        } elseif ($kind === 'APPEND' && $mode === 'APPEND') {
          $after = ($token !== '') ? ai_pr_replace_region($before, $token, $payload) : (rtrim($before) . "\n\n" . $payload . "\n");
        } else {
          WP_CLI::warning("Kind/mode mismatch: $kind (UI mode $mode). Skipping $rel");
          continue;
        }

        if ($dry) {
          $diff = strip_tags(ai_pr_unified_diff($before, $after));
          WP_CLI::line("=== PREVIEW: $rel ===\n$diff\n=== END PREVIEW ===");
        } else {
          if ($kind === 'FILE' && $mode === 'REPLACE') {
            ai_pr_apply_replace($abs, $after, $backup);
          } else {
            ai_pr_apply_append($abs, $payload, $backup, $token !== '' ? $token : null);
          }
          WP_CLI::success("Patched: $rel");
        }
      }
    }
  });
}

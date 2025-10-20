<?php
/**
 * Plugin Name: Elementor Widget Scope Fixer (MU)
 * Description: Scopes Elementor HTML widget CSS to a configurable wrapper; idempotent; heals double braces; strips @import and font-tail garbage; skips & repairs @keyframes; fixes invalid headings (h7–h9 → <h4 class="h8">…</h4>) and normalizes CSS selectors to .h8; enforces a single wrapper; dry-run/backup UI + CSV; WP-CLI with overrides.
 * Version: 1.9.0
 * Author: Ken + Helper
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

/* =========================================================================
 * CONFIG (defaults; override via Settings + WP-CLI)
 * ========================================================================= */
define('EWSF_VERSION',           '1.9.0');
define('EWSF_OPT_SCOPE',         'ewsf_house_scope');          // string, e.g. ".tri-county-widget"
define('EWSF_OPT_ALLOWLIST',     'ewsf_allowlist_prefixes');   // csv string: ".elementor-,.global-"
define('EWSF_BACKUP_DIR_SLUG',   'ai-backups');                // uploads subdir
define('EWSF_REPORTS_DIR_SLUG',  'reports');                   // uploads/ai-backups/reports
define('EWSF_MANIFEST_BASENAME', 'manifest.json');

function ewsf_get_house_scope(): string {
    $val = get_option(EWSF_OPT_SCOPE);
    $val = is_string($val) ? trim($val) : '';
    if ($val === '') { $val = '.tri-county-widget'; }
    if ($val[0] !== '.') { $val = '.' . $val; }
    return $val;
}
function ewsf_get_allowlist_prefixes(): array {
    $raw = get_option(EWSF_OPT_ALLOWLIST);
    $raw = is_string($raw) ? trim($raw) : '';
    if ($raw === '') { $raw = '.elementor-,.elementor,.e-con-,.global-,:root'; }
    $parts = array_filter(array_map('trim', explode(',', $raw)));
    $norm = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        // Keep as typed for :root, #id, pseudo etc.; normalize bare words to class-like
        if ($p[0] !== '.' && $p[0] !== '#' && $p[0] !== '[' && $p[0] !== ':' && $p[0] !== '*') {
            $p = '.' . $p;
        }
        $norm[] = $p;
    }
    return array_values(array_unique($norm));
}

/* =========================================================================
 * ADMIN UI
 * ========================================================================= */
add_action('admin_menu', function () {
    add_management_page(
        'Elementor Widget Scope Fixer',
        'Elementor Scope Fixer',
        'manage_options',
        'elementor-scope-fixer',
        'ewsf_admin_screen'
    );
});

function ewsf_admin_screen() {
    if (!current_user_can('manage_options')) { wp_die('Forbidden'); }

    $ids_input = isset($_POST['ewsf_ids']) ? trim(strval($_POST['ewsf_ids'])) : '';
    $mode      = isset($_POST['ewsf_mode']) ? trim(strval($_POST['ewsf_mode'])) : 'dry';
    $do_backup = !empty($_POST['ewsf_backup']);
    $scope_in  = isset($_POST['ewsf_house_scope']) ? trim(strval($_POST['ewsf_house_scope'])) : ewsf_get_house_scope();
    $allow_in  = isset($_POST['ewsf_allowlist']) ? trim(strval($_POST['ewsf_allowlist'])) : implode(',', ewsf_get_allowlist_prefixes());
    $result    = null;

    // Save settings
    if (!empty($_POST['ewsf_save_settings'])) {
        check_admin_referer('ewsf_settings');
        $scope = $scope_in !== '' ? $scope_in : ewsf_get_house_scope();
        if ($scope[0] !== '.') { $scope = '.' . $scope; }
        update_option(EWSF_OPT_SCOPE, $scope);
        update_option(EWSF_OPT_ALLOWLIST, $allow_in);
        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    // Run job
    if (!empty($_POST['ewsf_submit'])) {
        check_admin_referer('ewsf_run');
        $house     = $scope_in !== '' ? $scope_in : ewsf_get_house_scope();
        if ($house[0] !== '.') { $house = '.' . $house; }
        $allowlist = array_filter(array_map('trim', explode(',', $allow_in)));
        $ids = ewsf_parse_ids($ids_input);

        if (!empty($ids)) {
            if ($do_backup) { foreach ($ids as $pid) { ewsf_backup_post($pid); } }
            if ($mode === 'apply') {
                $result = ewsf_process_posts($ids, false, true, $house, $allowlist);
            } else {
                $result = ewsf_process_posts($ids, true, false, $house, $allowlist);
                if (!empty($result['csv'])) {
                    echo '<div class="notice notice-info"><p>CSV saved to: <code>' . esc_html($result['csv']) . '</code></p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error"><p>No valid IDs provided.</p></div>';
        }
    }

    $cur_scope = esc_attr(ewsf_get_house_scope());
    $cur_allow = esc_attr(implode(',', ewsf_get_allowlist_prefixes()));

    ?>
    <div class="wrap">
        <h1>Elementor Scope Fixer (MU) v<?php echo esc_html(EWSF_VERSION); ?></h1>

        <h2>Settings</h2>
        <form method="post" style="margin-bottom:1em;">
            <?php wp_nonce_field('ewsf_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ewsf_house_scope">Wrapper scope class</label></th>
                    <td>
                        <input type="text" id="ewsf_house_scope" name="ewsf_house_scope" value="<?php echo esc_attr($scope_in ?: $cur_scope); ?>" class="regular-text" />
                        <p class="description">Example: <code>.tri-county-widget</code>. If omitted, defaults to <?php echo esc_html($cur_scope); ?>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ewsf_allowlist">Allowlist prefixes</label></th>
                    <td>
                        <input type="text" id="ewsf_allowlist" name="ewsf_allowlist" value="<?php echo esc_attr($allow_in ?: $cur_allow); ?>" class="regular-text" />
                        <p class="description">Comma-separated list. Selectors that <em>start with</em> any of these prefixes will not be scoped. Example: <code>.elementor-,.elementor,.e-con-,.global-,:root</code></p>
                    </td>
                </tr>
            </table>
            <p><button class="button button-secondary" type="submit" name="ewsf_save_settings" value="1">Save Settings</button></p>
        </form>

        <h2>Run</h2>
        <form method="post">
            <?php wp_nonce_field('ewsf_run'); ?>
            <p><label for="ewsf_ids"><strong>Post/Page IDs (comma/space/newline separated)</strong></label></p>
            <textarea name="ewsf_ids" id="ewsf_ids" rows="4" style="width:100%;"><?php echo esc_textarea($ids_input); ?></textarea>
            <p>
                <label><input type="radio" name="ewsf_mode" value="dry" <?php checked(empty($mode) || $mode === 'dry'); ?>> Dry-run (no changes)</label>
                &nbsp;&nbsp;
                <label><input type="radio" name="ewsf_mode" value="apply" <?php checked($mode === 'apply'); ?>> Apply fixes (write back)</label>
                &nbsp;&nbsp;
                <label><input type="checkbox" name="ewsf_backup" value="1" <?php checked(!empty($do_backup)); ?>> Backup Elementor JSON before running</label>
            </p>

            <fieldset style="border:1px solid #ddd; padding:10px; margin-top:10px;">
                <legend><strong>Per-run overrides (optional)</strong></legend>
                <p>
                    <label for="ewsf_house_scope_run">Wrapper scope class (override)</label><br>
                    <input type="text" id="ewsf_house_scope_run" name="ewsf_house_scope" value="<?php echo esc_attr($scope_in); ?>" class="regular-text" />
                </p>
                <p>
                    <label for="ewsf_allowlist_run">Allowlist prefixes (override)</label><br>
                    <input type="text" id="ewsf_allowlist_run" name="ewsf_allowlist" value="<?php echo esc_attr($allow_in); ?>" class="regular-text" />
                </p>
                <p class="description">Leave blank to use saved Settings above.</p>
            </fieldset>

            <p><button class="button button-primary" type="submit" name="ewsf_submit" value="1">Run</button></p>
        </form>

        <?php if (isset($result) && is_array($result)) { ewsf_render_result_table($result); } ?>
    </div>
    <?php
}

function ewsf_render_result_table(array $result) {
    $rows = $result['rows'] ?? [];
    if (empty($rows)) {
        echo '<p>No HTML widgets found in the provided posts.</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>Post ID</th><th>Widgets</th><th>Changed</th><th>Notes</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        echo '<td>' . esc_html($r['post_id']) . '</td>';
        echo '<td>' . esc_html($r['widgets']) . '</td>';
        echo '<td>' . esc_html($r['changed']) . '</td>';
        echo '<td><code>' . esc_html($r['notes']) . '</code></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/* =========================================================================
 * WP-CLI
 * ========================================================================= */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('ai scope-fix', function ($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error('Usage: wp ai scope-fix <id...> [--apply] [--backup] [--house=".my-scope"] [--allowlist=".foo-,.bar-"]');
        }
        $apply     = !empty($assoc_args['apply']);
        $backup    = !empty($assoc_args['backup']);
        $house     = isset($assoc_args['house']) ? strval($assoc_args['house']) : ewsf_get_house_scope();
        $allowlist = isset($assoc_args['allowlist']) ? array_filter(array_map('trim', explode(',', strval($assoc_args['allowlist'])))) : ewsf_get_allowlist_prefixes();
        if ($house !== '' && $house[0] !== '.') { $house = '.' . $house; }

        $ids = array_values(array_filter(array_map('intval', array_filter($args, function ($a) { return is_numeric($a); }))));

        if (empty($ids)) { WP_CLI::error('No valid IDs provided.'); }

        if ($backup) { foreach ($ids as $pid) { ewsf_backup_post($pid); } }
        $res = ewsf_process_posts($ids, !$apply, $apply, $house, $allowlist);
        if (!empty($res['csv'])) { WP_CLI::log('CSV: ' . $res['csv']); }
        WP_CLI::success('Done.');
    });

    WP_CLI::add_command('ai scope-backup', function ($args) {
        if (empty($args)) { WP_CLI::error('Usage: wp ai scope-backup <id...>'); }
        $ids = array_values(array_filter(array_map('intval', $args)));
        foreach ($ids as $pid) { ewsf_backup_post($pid); }
        WP_CLI::success('Backups completed.');
    });
}

/* =========================================================================
 * UTIL: IDs & Files
 * ========================================================================= */
function ewsf_parse_ids(string $raw): array {
    $raw = preg_replace('/[^\d, \r\n\t]+/', ' ', $raw);
    $parts = preg_split('/[,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $ids = array_values(array_unique(array_map('intval', $parts)));
    return array_values(array_filter($ids));
}
function ewsf_get_upload_dir(): array {
    $up = wp_upload_dir();
    $base = trailingslashit($up['basedir']);
    $dir  = $base . EWSF_BACKUP_DIR_SLUG . '/';
    if (!is_dir($dir)) { wp_mkdir_p($dir); }
    return [$dir, $base];
}
function ewsf_backup_post(int $post_id): ?string {
    $data = get_post_meta($post_id, '_elementor_data', true);
    if (empty($data)) { return null; }
    list($dir) = ewsf_get_upload_dir();
    $stamp = gmdate('Ymd-His');
    $file  = $dir . 'elementor_' . $post_id . '_' . $stamp . '.json';
    file_put_contents($file, (is_string($data) ? $data : wp_json_encode($data)));
    // manifest
    $manifest = $dir . EWSF_MANIFEST_BASENAME;
    $entry = [
        'post_id' => $post_id,
        'file'    => basename($file),
        'time'    => current_time('mysql', true),
        'version' => EWSF_VERSION,
    ];
    $arr = [];
    if (is_file($manifest)) {
        $prev = json_decode(file_get_contents($manifest), true);
        if (is_array($prev)) { $arr = $prev; }
    }
    $arr[] = $entry;
    file_put_contents($manifest, wp_json_encode($arr, JSON_PRETTY_PRINT));
    return $file;
}

/* =========================================================================
 * CORE PROCESSOR (posts -> widgets -> HTML) — idempotent by design
 * ========================================================================= */
function ewsf_process_posts(array $post_ids, bool $dry_run, bool $apply, ?string $house_override = null, ?array $allowlist_override = null): array {
    $rows = [];
    $changes_total = 0;
    $csv_path = null; $csv = [];

    $house     = $house_override && $house_override !== '' ? $house_override : ewsf_get_house_scope();
    if ($house[0] !== '.') { $house = '.' . $house; }
    $allowlist = is_array($allowlist_override) && !empty($allowlist_override) ? $allowlist_override : ewsf_get_allowlist_prefixes();

    foreach ($post_ids as $post_id) {
        $raw = get_post_meta($post_id, '_elementor_data', true);
        if (empty($raw)) {
            $rows[] = ['post_id'=>$post_id, 'widgets'=>0, 'changed'=>0, 'notes'=>'no _elementor_data'];
            continue;
        }

        $json = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($json)) {
            $rows[] = ['post_id'=>$post_id, 'widgets'=>0, 'changed'=>0, 'notes'=>'invalid JSON'];
            continue;
        }

        $counter_widgets = 0;
        $counter_changed = 0;
        $notes = [];

        ewsf_walk_elements($json, function (&$node) use (&$counter_widgets, &$counter_changed, &$notes, $house, $allowlist) {
            if (!is_array($node)) { return; }
            $type = $node['elType'] ?? '';
            $widget = $node['widgetType'] ?? '';
            if ($type === 'widget' && $widget === 'html') {
                $counter_widgets++;
                $html = $node['settings']['html'] ?? '';
                $orig = $html;
                $new  = ewsf_process_html_widget($html, $house, $allowlist);
                if ($new !== $orig) {
                    $node['settings']['html'] = $new;
                    $counter_changed++;
                }
            }
        });

        if ($counter_changed > 0 && !$dry_run && $apply) {
            update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($json)));
            $notes[] = 'applied';
        } else {
            $notes[] = $dry_run ? 'dry-run' : 'no-op';
        }

        $rows[] = [
            'post_id' => $post_id,
            'widgets' => $counter_widgets,
            'changed' => $counter_changed,
            'notes'   => implode(';', $notes),
        ];

        $changes_total += $counter_changed;
        $csv[] = [$post_id, $counter_widgets, $counter_changed, implode(';', $notes)];
    }

    if ($dry_run) {
        list($dir) = ewsf_get_upload_dir();
        $repdir = $dir . EWSF_REPORTS_DIR_SLUG . '/';
        if (!is_dir($repdir)) { wp_mkdir_p($repdir); }
        $csv_path = $repdir . 'dryrun_' . gmdate('Ymd-His') . '.csv';
        $fp = fopen($csv_path, 'w');
        fputcsv($fp, ['post_id','widgets','changed','notes']);
        foreach ($csv as $line) { fputcsv($fp, $line); }
        fclose($fp);
    }

    return ['rows'=>$rows,'changed'=>$changes_total,'csv'=>$csv_path];
}

function ewsf_walk_elements(&$tree, callable $fn) {
    if (is_array($tree)) {
        if (isset($tree['elType'])) { $fn($tree); }
        if (isset($tree['elements']) && is_array($tree['elements'])) {
            foreach ($tree['elements'] as &$child) { ewsf_walk_elements($child, $fn); }
        } else {
            foreach ($tree as &$maybe) {
                if (is_array($maybe) && isset($maybe['elType'])) { ewsf_walk_elements($maybe, $fn); }
            }
        }
    }
}

/* =========================================================================
 * HTML WIDGET PROCESSOR — idempotent
 * ========================================================================= */
function ewsf_process_html_widget(string $html, string $house, array $allowlist): string {
    // Strip head-only tags (safe to repeat)
    $html = ewsf_strip_head_only_tags($html);

    // Heading tag repair (safe to repeat; avoids duplicate class)
    $html = ewsf_rewrite_invalid_headings_html($html);

    // Process <style> blocks (heals braces, scrubs import/garbage, scopes, repairs keyframes)
    $html = ewsf_process_style_blocks($html, $house, $allowlist);

    // Heal inline style="..." braces (idempotent)
    $html = ewsf_normalize_inline_styles($html);

    // Enforce a single wrapper and dedupe nested duplicates (idempotent)
    $html = ewsf_enforce_single_wrapper($html, $house);

    return $html;
}

function ewsf_strip_head_only_tags(string $html): string {
    $patterns = [
        '/<meta\b[^>]*>/i',
        '/<title\b[^>]*>.*?<\/title>/is',
        '/<link\b[^>]*rel=("|\')stylesheet\1[^>]*>/i',
        '/<\?xml[^>]*\?>/i', // stray XML decls from copy/paste
    ];
    return preg_replace($patterns, '', $html);
}

function ewsf_rewrite_invalid_headings_html(string $html): string {
    // h7–h9 -> <h4 class="h8">…, merging class safely and preventing duplicate "h8"
    $html = preg_replace_callback('/<(h)([789])\b([^>]*)>(.*?)<\/h\2>/is', function ($m) {
        $attrs = trim($m[3] ?? '');
        $body  = $m[4] ?? '';
        if (preg_match('/class=("|\')(.*?)\1/i', $attrs, $cm)) {
            $existing = preg_split('/\s+/', trim($cm[2]));
            if (!in_array('h8', $existing, true)) { $existing[] = 'h8'; }
            $merged = implode(' ', array_values(array_unique($existing)));
            $attrs = preg_replace('/class=("|\')(.*?)\1/i', 'class="' . esc_attr($merged) . '"', $attrs);
        } else {
            $attrs = trim($attrs) === '' ? 'class="h8"' : $attrs . ' class="h8"';
        }
        return '<h4 ' . trim($attrs) . '>' . $body . '</h4>';
    }, $html);
    return $html;
}

function ewsf_process_style_blocks(string $html, string $house, array $allowlist): string {
    return preg_replace_callback('/<style\b[^>]*>(.*?)<\/style>/is', function ($m) use ($house, $allowlist) {
        $css = $m[1];

        // 1) Remove @import lines (editor stability)
        $css = ewsf_remove_imports($css);

        // 2) Scrub common font-import garbage (e.g., "700&display=swap" tails)
        $css = ewsf_scrub_font_garbage($css);

        // 3) Normalize double braces (token-aware) and normalize h7–h9 selectors
        $css = ewsf_normalize_braces($css);
        $css = ewsf_normalize_h789_in_selectors($css);

        // 4) Repair malformed keyframe selectors like ".foo 100%{"
        $css = ewsf_repair_keyframe_selectors($css);

        // 5) Apply scope safely (skips inside keyframes; allowlist honored)
        $css = ewsf_scope_css_with_house($css, $house, $allowlist);

        return '<style>' . $css . '</style>';
    }, $html);
}

function ewsf_remove_imports(string $css): string {
    return preg_replace('/@import\s+[^;]+;/i', '', $css);
}

function ewsf_scrub_font_garbage(string $css): string {
    // Remove typical leftover fragments from pasted Google Fonts @import tails that break parsing
    // Examples: "700&display=swap');", "latin&display=swap');"
    $css = preg_replace('/[^{};]*display=swap[^{};]*;?/i', '', $css);
    // Remove orphan ");" following URL residue
    $css = preg_replace('/\)\s*;\s*/', ';', $css);
    return $css;
}

/* =========================================================================
 * CSS ENGINE: brace healing, h7–h9 normalization, scoping, keyframes-safe
 * ========================================================================= */
function ewsf_normalize_braces(string $css): string {
    $out = '';
    $len = strlen($css);
    $inStr = false;
    $inComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $css[$i];
        $next = ($i + 1 < $len) ? $css[$i + 1] : '';

        // comments
        if (!$inStr) {
            if (!$inComment && $ch === '/' && $next === '*') { $inComment = true; $out .= '/*'; $i++; continue; }
            if ($inComment && $ch === '*' && $next === '/') { $inComment = false; $out .= '*/'; $i++; continue; }
        }
        if ($inComment) { $out .= $ch; continue; }

        // strings
        if (!$inStr && ($ch === '"' || $ch === "'")) { $inStr = $ch; $out .= $ch; continue; }
        if ($inStr && $ch === $inStr) {
            $backslashes = 0; $j = $i - 1;
            while ($j >= 0 && $css[$j] === '\\') { $backslashes++; $j--; }
            if ($backslashes % 2 === 0) { $inStr = false; }
            $out .= $ch; continue;
        }

        // collapse brace runs when not in string/comment
        if ($ch === '{') {
            $out .= '{';
            while ($i + 1 < $len && $css[$i + 1] === '{') { $i++; }
            continue;
        }
        if ($ch === '}') {
            $out .= '}';
            while ($i + 1 < $len && $css[$i + 1] === '}') { $i++; }
            continue;
        }

        $out .= $ch;
    }
    return $out;
}

function ewsf_normalize_h789_in_selectors(string $css): string {
    // Whole-word tag selector h7|h8|h9 -> .h8
    return preg_replace('/(?<=^|[^\w-])h(?:7|8|9)(?=\b)/', '.h8', $css);
}

/**
 * Repair a common malformed keyframe selector: ".foo 100%{" -> "100% {"
 * Safe because "%" tokens do not occur in normal selectors.
 */
function ewsf_repair_keyframe_selectors(string $css): string {
    // Remove a leading ".class " immediately in front of from|to|NN% {
    $css = preg_replace('/\.[A-Za-z0-9_-]+\s+(?=(?:from|to|\d{1,3}%)(\s*\{))/i', '', $css);
    // Ensure a space before "{" for readability (optional)
    $css = preg_replace('/(\d{1,3}%|from|to)\{/', '$1 {', $css);
    return $css;
}

function ewsf_scope_css_with_house(string $css, string $house, array $allowlist): string {
    $out = '';
    $len = strlen($css);
    $i = 0;
    $depth = 0;
    $inStr = false;
    $inComment = false;
    $inKeyframes = false;

    // Build allowlist regex for "starts with" test
    $aw = array_map(function($p){ return preg_quote($p, '/'); }, $allowlist);
    $aw_regex = count($aw) ? '/^\s*(?:' . implode('|', $aw) . ')/' : null;

    $prefixSelectors = function (string $sel) use ($house, $aw_regex): string {
        // Split on commas not in parens/brackets
        $parts = [];
        $buf = '';
        $lp = 0; $lb = 0;
        $L = strlen($sel);
        for ($k = 0; $k < $L; $k++) {
            $c = $sel[$k];
            if ($c === '(') $lp++;
            if ($c === ')') $lp = max(0, $lp - 1);
            if ($c === '[') $lb++;
            if ($c === ']') $lb = max(0, $lb - 1);
            if ($c === ',' && $lp === 0 && $lb === 0) {
                $parts[] = trim($buf);
                $buf = '';
            } else {
                $buf .= $c;
            }
        }
        if (trim($buf) !== '') $parts[] = trim($buf);

        foreach ($parts as &$p) {
            $skip = ($aw_regex && preg_match($aw_regex, $p));
            if (!$skip) {
                if (!preg_match('/^\s*' . preg_quote($house, '/') . '\b/', $p)) {
                    $p = $house . ' ' . $p;
                }
            }
        }
        return implode(', ', $parts);
    };

    $prefixOutTailSelector = function () use (&$out, $prefixSelectors) {
        $pos = strrpos($out, '}');
        $start = ($pos === false) ? 0 : $pos + 1;
        $chunk = substr($out, $start);
        $trim  = trim($chunk);
        if ($trim !== '' && $trim[0] !== '@') {
            $pref = $prefixSelectors($trim);
            $out  = substr($out, 0, $start) . $pref;
        }
    };

    while ($i < $len) {
        $ch = $css[$i];
        $next = ($i + 1 < $len) ? $css[$i + 1] : '';

        // comments
        if (!$inStr) {
            if (!$inComment && $ch === '/' && $next === '*') { $inComment = true; $out .= '/*'; $i += 2; continue; }
            if ($inComment && $ch === '*' && $next === '/') { $inComment = false; $out .= '*/'; $i += 2; continue; }
        }
        if ($inComment) { $out .= $ch; $i++; continue; }

        // strings
        if (!$inStr && ($ch === '"' || $ch === "'")) { $inStr = $ch; $out .= $ch; $i++; continue; }
        if ($inStr) {
            $out .= $ch;
            if ($ch === $inStr) {
                $bs = 0; $j = $i - 1;
                while ($j >= 0 && $css[$j] === '\\') { $bs++; $j--; }
                if ($bs % 2 === 0) $inStr = false;
            }
            $i++; continue;
        }

        // detect @keyframes (any vendor)
        if (!$inKeyframes && $ch === '@') {
            $slice = strtolower(substr($css, $i, 20));
            if (strpos($slice, '@keyframes') === 0 || strpos($slice, '@-webkit-keyframes') === 0 || strpos($slice, '@-moz-keyframes') === 0 || strpos($slice, '@-o-keyframes') === 0) {
                $inKeyframes = true;
            }
            $out .= $ch; $i++; continue;
        }

        if ($ch === '{') {
            $depth++;
            if (!$inKeyframes) { $prefixOutTailSelector(); }
            $out .= '{';
            $i++; continue;
        }

        if ($ch === '}') {
            $depth = max(0, $depth - 1);
            if ($inKeyframes && $depth === 0) { $inKeyframes = false; }
            $out .= '}';
            $i++; continue;
        }

        $out .= $ch;
        $i++;
    }

    return $out;
}

/* =========================================================================
 * INLINE STYLE HEAL + SINGLE WRAPPER (idempotent)
 * ========================================================================= */
function ewsf_normalize_inline_styles(string $html): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    foreach ($xp->query('//*[@style]') as $el) {
        /** @var DOMElement $el */
        $style = $el->getAttribute('style');
        if ($style !== '') {
            $fixed = ewsf_normalize_braces($style);
            if ($fixed !== $style) { $el->setAttribute('style', $fixed); }
        }
    }
    $out = $dom->saveHTML();
    return preg_replace('/^<\?xml.*?\?>/i', '', $out);
}

function ewsf_enforce_single_wrapper(string $html, string $house): string {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();

    $houseClass = ltrim($house, '.');
    $xp = new DOMXPath($dom);
    $nodes = $xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $houseClass . " ')]");

    if ($nodes->length > 0) {
        // Remove nested duplicates (keep the first)
        for ($i = 1; $i < $nodes->length; $i++) {
            $n = $nodes->item($i);
            while ($n->hasChildNodes()) { $n->parentNode->insertBefore($n->firstChild, $n); }
            $n->parentNode->removeChild($n);
        }
        $out = $dom->saveHTML();
        return preg_replace('/^<\?xml.*?\?>/i', '', $out);
    }

    // Otherwise wrap once
    $wrapper = $dom->createElement('div');
    $wrapper->setAttribute('class', $houseClass);
    $wrapper->setAttribute('data-ai-scoped', '1');

    while ($dom->childNodes->length) {
        $wrapper->appendChild($dom->childNodes->item(0));
    }
    $dom->appendChild($wrapper);

    $out = $dom->saveHTML();
    return preg_replace('/^<\?xml.*?\?>/i', '', $out);
}

/* =========================================================================
 * REPORTING HELPERS
 * ========================================================================= */
function ewsf_report_path_after_last_dryrun(): ?string {
    list($dir) = ewsf_get_upload_dir();
    $repdir = $dir . EWSF_REPORTS_DIR_SLUG . '/';
    if (!is_dir($repdir)) { return null; }
    $files = glob($repdir . 'dryrun_*.csv');
    if (empty($files)) { return null; }
    usort($files, function($a,$b){ return strcmp($b,$a); });
    return $files[0];
}

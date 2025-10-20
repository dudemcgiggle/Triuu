<?php
/**
 * Plugin Name: OpenAI Content Editor (MU)
 * Description: Admin tool to generate content with OpenAI and write it into a post/page (replace or append).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

add_action('admin_menu', function () {
    add_management_page(
        'OpenAI Content Editor',
        'OpenAI Content Editor',
        'edit_posts',
        'openai-content-editor',
        'openai_content_editor_screen'
    );
});

function openai_content_editor_screen() {
    if (!current_user_can('edit_posts')) { wp_die('Forbidden'); }

    if (!function_exists('openai_wp_chat')) {
        echo '<div class="notice notice-error"><p>Missing dependency: openai_wp_chat() not found. Ensure openai-wp-service.php exists in mu-plugins.</p></div>';
        return;
    }

    $updated = false;
    $error   = '';
    $result  = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('openai_content_editor')) {
        $target      = sanitize_text_field($_POST['target'] ?? '');
        $mode        = in_array($_POST['mode'] ?? '', array('replace','append'), true) ? $_POST['mode'] : 'append';
        $prompt      = wp_unslash($_POST['prompt'] ?? '');
        $max_tokens  = max(30, (int)($_POST['max_tokens'] ?? 300));
        $temperature = min(1.0, max(0.0, (float)($_POST['temperature'] ?? 0.3)));

        $post_id = 0;
        if (preg_match('/^\d+$/', $target)) {
            $post_id = (int)$target;
        } else {
            $page = get_page_by_path(sanitize_title($target), OBJECT, array('post','page'));
            if ($page) { $post_id = (int)$page->ID; }
        }

        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            $error = 'Invalid target post/page or insufficient permissions.';
        } elseif (empty($prompt)) {
            $error = 'Prompt is required.';
        } else {
            $messages = array(
                array('role' => 'system', 'content' => 'You write concise, clean HTML for WordPress pages. Avoid <script>. Use headings, paragraphs, and lists.'),
                array('role' => 'user',   'content' => $prompt)
            );
            $result = openai_wp_chat($messages, array(
                'model'       => 'gpt-4o-mini',
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
                'cache_ttl'   => 0
            ));

            if (is_wp_error($result)) {
                $error = 'OpenAI error: ' . $result->get_error_message();
            } else {
                $new = (string)($result['content'] ?? '');
                $post = get_post($post_id);
                if (!$post) {
                    $error = 'Target post not found.';
                } else {
                    $updated_content = ($mode === 'replace')
                        ? $new
                        : trim($post->post_content . "\n\n" . $new);

                    $ok = wp_update_post(array(
                        'ID'           => $post_id,
                        'post_content' => wp_kses_post(wp_slash($updated_content))
                    ), true);

                    if (is_wp_error($ok)) { $error = 'wp_update_post failed: ' . $ok->get_error_message(); }
                    else { $updated = true; }
                }
            }
        }
    }
    ?>
    <div class="wrap">
      <h1>OpenAI Content Editor</h1>
      <?php if ($updated): ?>
        <div class="notice notice-success"><p>Updated post successfully.</p></div>
      <?php elseif ($error): ?>
        <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
      <?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('openai_content_editor'); ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label for="target">Target Post/Page (ID or slug)</label></th>
            <td><input name="target" id="target" type="text" class="regular-text" required placeholder="e.g. 42 or about-us"></td>
          </tr>
          <tr>
            <th scope="row">Mode</th>
            <td>
              <label><input type="radio" name="mode" value="replace"> Replace content</label><br>
              <label><input type="radio" name="mode" value="append" checked> Append to existing content</label>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="prompt">Prompt</label></th>
            <td><textarea name="prompt" id="prompt" rows="7" class="large-text" required placeholder="Describe what you want written..."></textarea></td>
          </tr>
          <tr>
            <th scope="row"><label for="max_tokens">Max tokens</label></th>
            <td><input name="max_tokens" id="max_tokens" type="number" min="30" step="10" value="300"></td>
          </tr>
          <tr>
            <th scope="row"><label for="temperature">Temperature</label></th>
            <td><input name="temperature" id="temperature" type="number" min="0" max="1" step="0.1" value="0.3"></td>
          </tr>
        </table>
        <?php submit_button('Generate and Update'); ?>
      </form>

      <?php if (is_array($result) && !empty($result['content'])): ?>
        <h2>Preview of Generated Content</h2>
        <div style="border:1px solid #ddd; padding:12px; background:#fff">
          <?php echo wp_kses_post($result['content']); ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

<?php
/**
 * Plugin Name: OpenAI WP Service (MU)
 * Description: Thin helper to call OpenAI Chat Completions via WordPress HTTP API; no Composer required.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

if (!function_exists('openai_wp_get_key')) {
    function openai_wp_get_key() {
        $k = getenv('OPENAI_API_KEY');
        if (!$k && isset($_ENV['OPENAI_API_KEY'])) { $k = $_ENV['OPENAI_API_KEY']; }
        if (!$k && defined('OPENAI_API_KEY')) { $k = OPENAI_API_KEY; } // optional wp-config.php fallback
        return $k;
    }
}

if (!function_exists('openai_wp_chat')) {
    /**
     * Call OpenAI chat/completions the WP-native way.
     *
     * @param array $messages  OpenAI messages array: [ ['role'=>'system'|'user'|'assistant', 'content'=>'...'], ... ]
     * @param array $args      Options: model, max_tokens, temperature, timeout, cache_ttl
     * @return array|WP_Error  On success: ['content'=>string, 'model'=>string, 'usage'=>array|null, 'raw'=>array]
     */
    function openai_wp_chat( $messages, $args = array() ) {
        if (!is_array($messages) || empty($messages)) {
            return new WP_Error('bad_messages', 'messages must be a non-empty array');
        }

        $defaults = array(
            'model'       => 'gpt-4o-mini',
            'max_tokens'  => 300,
            'temperature' => 0.2,
            'timeout'     => 30,
            'cache_ttl'   => 0   // seconds; 0 = no cache
        );
        $args = wp_parse_args($args, $defaults);

        $key = openai_wp_get_key();
        if (!$key) { return new WP_Error('no_key', 'OPENAI_API_KEY not found'); }

        // Optional response cache (by prompt + key parameters).
        if ($args['cache_ttl'] > 0) {
            $cache_key = 'openai_' . md5( wp_json_encode( array($messages, $args['model'], $args['max_tokens'], $args['temperature']) ) );
            $cached = get_transient($cache_key);
            if ($cached !== false) { return $cached; }
        }

        $payload = array(
            'model'       => $args['model'],
            'messages'    => $messages,
            'max_tokens'  => (int) $args['max_tokens'],
            'temperature' => (float) $args['temperature']
        );

        $http_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json'
            ),
            'body'        => wp_json_encode($payload),
            'timeout'     => (int) $args['timeout'],
            'data_format' => 'body'
        );

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $http_args);
        if (is_wp_error($res)) { return new WP_Error('http_error', $res->get_error_message()); }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if ($code !== 200 || !is_array($json)) {
            return new WP_Error('api_error', 'OpenAI API error', array('status' => $code ?: 500, 'body' => $json ?: $raw));
        }

        $content = '';
        if (isset($json['choices'][0]['message']['content'])) {
            $content = (string) $json['choices'][0]['message']['content'];
        }

        $result = array(
            'content' => $content,
            'model'   => isset($json['model']) ? $json['model'] : $args['model'],
            'usage'   => isset($json['usage']) ? $json['usage'] : null,
            'raw'     => $json
        );

        if ($args['cache_ttl'] > 0) {
            set_transient($cache_key, $result, (int) $args['cache_ttl']);
        }

        return $result;
    }
}

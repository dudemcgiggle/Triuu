<?php
/**
 * SQLite Plugin Downloader
 * Access via: curl -o db.php http://localhost:5000/download-plugin.php
 */

$plugin_file = __DIR__ . '/wp-content/db.php';

if (!file_exists($plugin_file)) {
    http_response_code(404);
    die("Plugin file not found at: $plugin_file");
}

$size = filesize($plugin_file);
if ($size < 100000) {
    http_response_code(500);
    die("Plugin file seems corrupted (size: $size bytes)");
}

// Set headers for plain text download
header('Content-Type: text/plain');
header('Content-Length: ' . $size);
header('X-Plugin-Size: ' . $size);

// Output the file
readfile($plugin_file);
exit;

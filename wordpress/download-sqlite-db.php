<?php
/**
 * Download helper for SQLite db.php plugin
 * Access this file from your browser to download the SQLite plugin
 */

$source_file = __DIR__ . '/wp-content/db.php.sqlite.backup';

if (!file_exists($source_file)) {
    die('Error: SQLite plugin backup file not found at: ' . $source_file);
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="db.php"');
header('Content-Length: ' . filesize($source_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Output the file
readfile($source_file);
exit;

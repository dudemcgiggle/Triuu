<?php
/**
 * Download the SQLite plugin installer script
 */

$script_file = __DIR__ . '/install-db-php.sh';

if (!file_exists($script_file)) {
    die('Error: Installer script not found');
}

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="install-db-php.sh"');
header('Content-Length: ' . filesize($script_file));

readfile($script_file);
exit;

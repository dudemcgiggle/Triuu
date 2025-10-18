<?php
/**
 * Router script for PHP built-in server running WordPress
 * This handles routing for WordPress when using PHP's built-in web server
 */

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If the file exists and is not a PHP file, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . '/wordpress' . $uri)) {
    return false;
}

// Otherwise, route everything through WordPress
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/wordpress/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir(__DIR__ . '/wordpress');
require __DIR__ . '/wordpress/index.php';

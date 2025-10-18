<?php
/**
 * Router script for PHP built-in server running WordPress
 * This handles routing for WordPress when using PHP's built-in web server
 */

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Handle admin requests - let WordPress process them
if (strpos($uri, '/wp-admin/') === 0 || $uri === '/wp-admin') {
    // Set working directory
    chdir(__DIR__ . '/wordpress');
    // Don't modify SCRIPT variables for admin pages - let them be handled naturally
    return false;
}

// If it's a wp-login.php request, let it through
if (strpos($uri, '/wp-login.php') !== false) {
    chdir(__DIR__ . '/wordpress');
    return false;
}

// If the file exists and is not a PHP file in wp-content or wp-includes, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . '/wordpress' . $uri)) {
    // Serve static assets directly (CSS, JS, images, fonts, etc.)
    if (!preg_match('/\.php$/', $uri)) {
        return false;
    }
}

// For everything else, route through WordPress front-end
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/wordpress/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir(__DIR__ . '/wordpress');
require __DIR__ . '/wordpress/index.php';

<?php
/**
 * WordPress Configuration for Replit
 */

// ** SQLite Database Configuration ** //
define( 'DB_DIR', dirname(__FILE__) . '/wp-content/database/' );
define( 'DB_FILE', 'wordpress.db' );

// For SQLite compatibility (these are ignored but required)
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 */
define('AUTH_KEY',         '!!m|$x3~DlMAI]#aLf)]5ZKd~E6PwWxq}L]?g$8Xn|(OC]_B`Km{n4M/5oImt-Mt');
define('SECURE_AUTH_KEY',  'u~F`MZHTi|25-_eu[OT2+_jbXZ<`Y}5<J@SjCaEDXv]lJrbL|S.B4cNHtV~)rSDh');
define('LOGGED_IN_KEY',    '?+}Nw~||WP@w&,hS3x8|OI+piEEKpIXA;[V|pr?R}y~)2>4c{FKa+1KrS)+5q- Q');
define('NONCE_KEY',        '22hoPPWVM*KkWPySI@VV~{OpFI-?C271~3YBguu3dypVKg!L:S{JONix}cTODoH$');
define('AUTH_SALT',        'TXD<}GNWskE - W+tB>k#|m{W$FQM8-M2[b_{5Of^*|]k9z}6kU]FGtVXx40N9p2');
define('SECURE_AUTH_SALT', 'M)>cN-ECB#m-q#1p-s XIPKQrryQE+BJJ0;fu3gO(!tLjn3a+z(IcESes`.d`w8?');
define('LOGGED_IN_SALT',   'wAky$8zFhQ.:$FeqO.q*E,/Wc_KxdO)t2.*-|MGk$#(,-pH|Unr5)Yo%2mSdp.h:');
define('NONCE_SALT',       'Or(.{V+_8|4 1ycP6v|o3s(5r&{bKP8qqRfAV{0@?e||hIW,tQap.|-$l)~*n;qh');
/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'RYX_';

/**
 * For developers: WordPress debugging mode.
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/**
 * Replit environment configuration
 */
// Fix for proxy/load balancer - tell WordPress it's being served over HTTPS
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// Secure domain configuration - prioritize proxy headers, then Replit env, then HTTP_HOST
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    // Extract first host from X-Forwarded-Host header (remove port if present)
    $forwarded_hosts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
    $site_domain = trim(explode(':', $forwarded_hosts[0])[0]);
    // Update HTTP_HOST to match the public domain for cookie/auth compatibility
    $_SERVER['HTTP_HOST'] = $site_domain;
} else {
    // Fallback to Replit environment variables or HTTP_HOST
    $replit_domain = getenv('REPLIT_DEV_DOMAIN') ?: getenv('REPLIT_DOMAINS');
    $site_domain = $replit_domain ?: $_SERVER['HTTP_HOST'];
}

// Force HTTPS for all WordPress URLs
define( 'WP_HOME', 'https://' . $site_domain );
define( 'WP_SITEURL', 'https://' . $site_domain );
define( 'FORCE_SSL_ADMIN', true );

// File system method
define( 'FS_METHOD', 'direct' );

// Disable file modifications from admin
define( 'DISALLOW_FILE_EDIT', false );
define( 'DISALLOW_FILE_MODS', false );

// Memory limits
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '256M' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

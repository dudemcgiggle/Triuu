<?php
/**
 * The base configuration for WordPress
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'azfejate_WPTOB');

/** Database username */
define('DB_USER', 'azfejate_WPTOB');

/** Database password */
define('DB_PASSWORD', 'xuv:2&e}]{{TT/w_w');

/** Database hostname */
define('DB_HOST', 'localhost');

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 * Change these to different unique phrases!
 * @since 2.6.0
 */
define('AUTH_KEY',         '031c649307e06597868e98951ab511111f65810fe425ad2a70e3ad61bde63d1f');
define('SECURE_AUTH_KEY',  '4990c83dcba61a0144fac6493e28094174cd12f3d92255502fc713f7941abdd3');
define('LOGGED_IN_KEY',    '965c9e99b6d69523d56f8dd3943d2215d8b5fff89e7a7d1e3373f6c50fd3e40f');
define('NONCE_KEY',        '165f588fe0780685b8969362488eca2aee6de79aabee708745ba519a856cc93e');
define('AUTH_SALT',        '2238a562936e65d9eca6a31a2cb80c0dd6b09a80dfa53d1432ef7e5a70adc8fa');
define('SECURE_AUTH_SALT', 'bec72d917f3b109bc96acc9dde4717bfb5962e194149ac7221211ec7c10e1ce4');
define('LOGGED_IN_SALT',   'f65dc469e1b79523142251486941c6e23c8cc4ae80e109e6878651a91dc628a1');
define('NONCE_SALT',       '11edfb26432e3c59a0f95792d9b3c53ad6365729b0cfe120c798e40899cba70e');
/**#@-*/

/**
 * WordPress database table prefix.
 * Only numbers, letters, and underscores please!
 */
$table_prefix = 'RYX_';

/** Core behavior tweaks */
define('WP_CRON_LOCK_TIMEOUT', 120);
define('AUTOSAVE_INTERVAL', 300);
define('WP_POST_REVISIONS', 20);
define('EMPTY_TRASH_DAYS', 7);
define('WP_AUTO_UPDATE_CORE', true);

/**
 * Debugging mode.
 * Set to true to enable debug notices.
 */
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */

/** Increase PHP memory available to WordPress */
define('WP_MEMORY_LIMIT', '256M');      // Front end / common context
define('WP_MAX_MEMORY_LIMIT', '512M');  // Admin, image ops, heavy tasks

/** PDF→HTML microservice (bridge to your FastAPI backend) */
define('PDF2HTML_SERVICE_URL', 'http://127.0.0.1:8000');           // or your reverse-proxied URL
define('PDF2HTML_BRIDGE_TOKEN', 'REPLACE_WITH_YOUR_BRIDGE_TOKEN');  // must match BRIDGE_TOKEN in the Python service .env

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

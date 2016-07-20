<?php
######################################################################################
##### We highly suggest not put anything sensitive in this file directly         #####
##### Use a separate .env file instead that overwrites the defaults.             #####
##### Read more at                                                               #####
##### https://docs.wp-palvelu.fi/development/configure-vagrant-box/#using-dotenv #####
######################################################################################

#Load composer libraries
require_once(dirname(__DIR__) . '/vendor/autoload.php');

$root_dir = dirname(__DIR__);
$webroot_dir = $root_dir . '/htdocs';

/**
 * Use Dotenv to set required environment variables and load .env file in root
 * WP-Palvelu provides all needed envs for wordpress by default.
 * If you want to have more envs put them into .env file
 * .env file is also heavily used in development
 */
if (file_exists($root_dir . '/.env')) {
  Dotenv::makeMutable();
  Dotenv::load($root_dir);
}


/**
 * DB settings
 * You can find the credentials by running $ wp-list-env
 */
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_PORT', getenv('DB_PORT') ? getenv('DB_PORT') : 3306 );
define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') .':'. DB_PORT : 'localhost:' . DB_PORT );
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = getenv('DB_PREFIX') ? getenv('DB_PREFIX') : 'wp_';

/**
 * Content Directory is moved out of the wp-core.
 */
define('CONTENT_DIR', '/wp-content');
define('WP_CONTENT_DIR', $webroot_dir . CONTENT_DIR);
define('WP_CONTENT_URL', CONTENT_DIR);

/**
 * Don't allow any other write method than direct
 */
define( 'FS_METHOD', 'direct' );

/**
 * Authentication Unique Keys and Salts
 * You can find them by running $ wp-list-env
 */
define('AUTH_KEY',         getenv('AUTH_KEY'));
define('SECURE_AUTH_KEY',  getenv('SECURE_AUTH_KEY'));
define('LOGGED_IN_KEY',    getenv('LOGGED_IN_KEY'));
define('NONCE_KEY',        getenv('NONCE_KEY'));
define('AUTH_SALT',        getenv('AUTH_SALT'));
define('SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT'));
define('LOGGED_IN_SALT',   getenv('LOGGED_IN_SALT'));
define('NONCE_SALT',       getenv('NONCE_SALT'));

/**
 * SSL ADMIN
 * Allow overriding it in dev environment so we can use phantomjs to test logging in.
 */
defined('FORCE_SSL_ADMIN') or define('FORCE_SSL_ADMIN', true);

/**
 * Use *.wp-palvelu.fi domain as the wp-admin
 */
if (getenv('HTTPS_DOMAIN_ALIAS'))
  define('HTTPS_DOMAIN_ALIAS', getenv('HTTPS_DOMAIN_ALIAS'));

/**
 * Custom Settings
 */
define('AUTOMATIC_UPDATER_DISABLED', true); /* automatic updates are handled by wordpress-palvelu */
define('DISALLOW_FILE_EDIT', true); /* this disables the theme/plugin file editor */
define('PLL_COOKIE', false); /* this allows caching sites with polylang, disable if weird issues occur */

/*
 * Auto activated plugins
 * - These plugins will be activated automatically when this is installed
 */
define('WP_AUTO_ACTIVATE_PLUGINS',"google-analytics-dashboard-for-wp");

/**
 * Only keep the last 30 revisions of a post. Having hundreds of revisions of
 * each post might cause sites to slow down, sometimes significantly due to a
 * massive, and usually unecessary bloating the wp_posts and wp_postmeta tables.
 */
define( 'WP_POST_REVISIONS', 30 );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

/**
 * Log error data but don't show it in the frontend.
 */
ini_set('log_errors', 'On');

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
  define('ABSPATH', $webroot_dir . '/wordpress/');
}

require_once(ABSPATH . 'wp-settings.php');

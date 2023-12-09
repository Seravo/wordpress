<?php
###############################################################################
##### Don't put anything sensitive (such as passwords) in this file.      #####
##### Use a separate .env file instead that overwrites the defaults.      #####
##### Read more at                                                        #####
##### https://seravo.com/docs/development/environment-variables/          #####
###############################################################################

// Load Composer libraries
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Use Dotenv to load environment variables from a .env file at project root.
 * Seravo provides all needed envs for WordPress by default. For details see
 * https://seravo.com/docs/development/environment-variables/#using-dotenv
 *
 * The syntax below is written for Dotenv 5.x. For changes to previous versions
 * see  https://github.com/vlucas/phpdotenv/blob/master/UPGRADING.md
 */
if ( file_exists(dirname(__DIR__) . '/.env') ) {
  $dotenv = Dotenv\Dotenv::createUnsafeImmutable(dirname(__DIR__));
  $dotenv->load();
}

/**
 * DB settings
 * You can find the credentials by running $ wp-list-env
 */
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASSWORD', getenv('DB_PASSWORD'));
define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1:3306');
define('DB_CHARSET', getenv('DB_CHARSET') ? getenv('DB_CHARSET') : 'utf8mb4');
define('DB_COLLATE', getenv('DB_COLLATE') ? getenv('DB_COLLATE') : 'utf8mb4_swedish_ci');
$table_prefix = getenv('DB_PREFIX') ? getenv('DB_PREFIX') : 'wp_';

/**
 * Content Directory is moved out of the wp-core.
 */
define('CONTENT_DIR', '/wp-content');
define('WP_CONTENT_DIR', dirname(__DIR__) . '/htdocs' . CONTENT_DIR);

// WP_CONTENT_URL can be set to enable relative URLs to /wp-content
// but if undefined, it simply defaults to absolute URLs.
// define('WP_CONTENT_URL', CONTENT_DIR);

/**
 * Don't allow any other write method than direct
 */
define('FS_METHOD', 'direct');

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
 * Always enforce https when accessing the login and wp-admin pages
 */
define('FORCE_SSL_ADMIN', true);

/**
 * Disable automatic updates and use Seravo updates instead
 */
define('AUTOMATIC_UPDATER_DISABLED', true);

/*
 * Disable the theme/plugin file editor for security reasons
 */
define('DISALLOW_FILE_EDIT', true);

/*
 * Prevent Polylang from setting extra language cookies, so that the HTTP cache
 * is not busted in vain. The language is anyway in the request URL (e.g. /en/)
 * so extra language cookies are not needed.
 */
define('PLL_COOKIE', false);

/**
 * Standardize cache location to have as much as possible in wp-content/cache
 * so that it is easier to develop tools to accelerate or purge caches.
 */
define('WPML_CACHE_PATH_ROOT', dirname(__DIR__) . '/htdocs/wp-content/cache/wpml/');

/**
 * Only keep the last 30 revisions of a post. Having hundreds of revisions of
 * each post might cause sites to slow down, sometimes significantly due to a
 * massive, and usually unecessary bloating the wp_posts and wp_postmeta tables.
 */
define('WP_POST_REVISIONS', 30);

/**
 * Namespace session cookies so that overlapping cookie names would not result
 * in deleting session when users are switching between production and a shadow
 * instances. Using a clear-text container name does no harm. The default value
 * is a fully remote predictable md5 hash of the siteurl.
 */
define('COOKIEHASH', getenv('CONTAINER'));

/**
 * For developers: show verbose debugging output if not in production.
 */
if ( 'production' === getenv('WP_ENV') ) {
  define('WP_DEBUG', false);
  define('WP_DEBUG_DISPLAY', false);
  define('WP_DEBUG_LOG', false);
  define('SCRIPT_DEBUG', false);
} else {
  define('WP_DEBUG', true);
  define('WP_DEBUG_DISPLAY', true);
  define('WP_DEBUG_LOG', '/data/log/php-error.log');
  define('SCRIPT_DEBUG', true);
  define('WP_DEVELOPMENT_MODE', 'all');
}

/**
 * Log error data but don't show it in the frontend.
 */
ini_set('log_errors', 'On');

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( ! defined('ABSPATH') ) {
  define('ABSPATH', dirname(__DIR__) . '/htdocs/wordpress/');
}

require_once ABSPATH . 'wp-settings.php';

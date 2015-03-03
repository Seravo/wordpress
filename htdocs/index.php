<?php

/**
 * HHVM outputs http status: 200 OK even on fatal errors.
 * Catch fatal errors on HHVM and put right response code.
 */

//Is this HHVM?
if (defined('HHVM_VERSION')) {
  set_error_handler('catch_fatal_error_hhvm',E_ERROR);
}
function catch_fatal_error_hhvm() {
  http_response_code(500);
  die();
}

// WordPress view bootstrapper
define('WP_USE_THEMES', true);
require(dirname( __FILE__ ) . '/wordpress/wp-blog-header.php');

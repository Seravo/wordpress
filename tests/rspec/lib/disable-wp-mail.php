<?php
/**
 * By default, whenever a password is changed, WordPress sends a password change notification
 * As our tests frequently change the test user password, this is an undesireable behaviour
 * and we want to disable it.
 *
 * WP-CLI will require this file before doing a password change.
 */
if( !function_exists( 'wp_mail' ) ) {
  function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
    return false;
  }
}


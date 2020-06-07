<?php

namespace WordPress;

use Composer\Script\Event;

class Installer {
  /**
   * Fully delete the default wp-content from WordPress and replace it with a
   * symlink it to ../wp-content. This also removes the default theme and plugins.
   *
   * @param Composer\Script\Event $event - This is the way composer talks to it's plugins
   */
  public static function symlinkWPContent( Event $event ) {
    $io = $event->getIO();
    $root = dirname(dirname(__DIR__));
    $wp_core_content_folder = "{$root}/htdocs/wordpress/wp-content";
    $wp_content_folder = "{$root}/htdocs/wp-content";

    if ( ! is_link($wp_core_content_folder) ) {
      if ( file_exists($wp_core_content_folder) ) {
        self::rrmdir($wp_core_content_folder);
        if ( self::is_windows() ) {
          $io->write('Windows: Removed wp-content from core');
        } else {
          // Symlink shouldn't be necessary
          // It just makes everything seem more normal and fixes some problems with bad plugins
          symlink('../wp-content', $wp_core_content_folder);
          $io->write('Removed wp-content from core and symlinked it to ../wp-content');
        }
      }
    }
  }

  /**
   * Remove dir recursively
   *
   * @param String $dir - path to folder to be destroyed
   */
  public static function rrmdir( $dir ) {
    // Delete all dotfiles first since the latter '*' will not catch them
    // this is needed for exampel to delete:
    //   - plugins/akismet/.htaccess
    //   - themes/twentytwenty/.stylelintrc.json
    foreach ( glob($dir . '/.*') as $file ) {
      // The glob above will also match directories, so check match type first
      // and only delete files
      if ( is_file($file) ) {
        unlink($file);
      }
    }
    foreach ( glob($dir . '/*') as $file ) {
      if ( is_dir($file) ) {
        self::rrmdir($file);
      } else {
        unlink($file);
      }
    }
    rmdir($dir);
  }

  /**
   * Check if this is windows
   *
   * @return bool
   */
  private static function is_windows() {
    if ( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
      return true;
    } else {
      return false;
    }
  }
}

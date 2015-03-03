<?php

namespace Wordpress;

use Composer\Script\Event;

class Installer {
  /**
  * Remove wp-content from wordpress and symlink it to correct location
  */
  public static function symlinkWPContent(Event $event) {
    $io = $event->getIO();
    $root = dirname(dirname(__DIR__));
    $wp_core_content_folder = "{$root}/htdocs/wordpress/wp-content";
    $wp_content_folder = "{$root}/htdocs/wp-content";

    if (!is_link($wp_core_content_folder)) {
      if(file_exists($wp_core_content_folder)) {
        Installer::rrmdir($wp_core_content_folder);
      }
      symlink("../wp-content", $wp_core_content_folder);
      $io->write("Removed wp-content from core and symlinked it to {$wp_content_folder}");
    }
  }

  //Remove dir recursively
  public static function rrmdir($dir) {
    foreach(glob($dir . '/*') as $file) {
      if(is_dir($file)) Installer::rrmdir($file); else unlink($file);
    } rmdir($dir);
  }
}
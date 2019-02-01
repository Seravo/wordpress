<?php
/**
 * WordPress custom install script.
 *
 * Drop-ins are advanced plugins in the wp-content directory that replace WordPress functionality when present.
 *
 * Language: fi
 */

/**
 * Installs the site.
 *
 * Runs the required functions to set up and populate the database,
 * including primary admin user and initial options.
 *
 * @since 2.1.0
 *
 * @param string $blog_title    Blog title.
 * @param string $user_name     User's username.
 * @param string $user_email    User's email.
 * @param bool   $public        Whether blog is public.
 * @param string $deprecated    Optional. Not used.
 * @param string $user_password Optional. User's chosen password. Default empty (random password).
 * @param string $language      Optional. Language chosen. Default empty.
 * @return array Array keys 'url', 'user_id', 'password', and 'password_message'.
 */
function wp_install( $blog_title, $user_name, $user_email, $public, $deprecated = '', $user_password = '', $language = '' ) {
  if ( !empty( $deprecated ) )
    _deprecated_argument( __FUNCTION__, '2.6' );

  wp_check_mysql_version();
  wp_cache_flush();
  make_db_current_silent();
  populate_options();
  populate_roles();

  update_option('blogname', $blog_title);
  update_option('admin_email', $user_email);
  update_option('blog_public', $public);

  // Freshness of site - in the future, this could get more specific about actions taken, perhaps.
  update_option( 'fresh_site', 1 );

  // Seravo: Pickup environment defined language
  $env_wp_lang = getenv('WP_LANG');

  // Seravo: Load the text domain for environment variable
  if ( ! $language && $env_wp_lang) {
      $language = $env_wp_lang;

      load_default_textdomain( $env_wp_lang );
      $GLOBALS['wp_locale'] = new WP_Locale();
  }

  if ( $language ) {
    update_option( 'WPLANG', $language );
  }

  $guessurl = wp_guess_url();

  update_option('siteurl', $guessurl);

  // If not a public blog, don't ping.
  if ( ! $public )
    update_option('default_pingback_flag', 0);

  /*
   * Create default user. If the user already exists, the user tables are
   * being shared among blogs. Just set the role in that case.
   */
  $user_id = username_exists($user_name);
  $user_password = trim($user_password);
  $email_password = false;
  if ( !$user_id && empty($user_password) ) {
    $user_password = wp_generate_password( 12, false );
    $message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
    $user_id = wp_create_user($user_name, $user_password, $user_email);
    update_user_option($user_id, 'default_password_nag', true, true);
    $email_password = true;
  } elseif ( ! $user_id ) {
    // Password has been provided
    $message = '<em>'.__('Your chosen password.').'</em>';
    $user_id = wp_create_user($user_name, $user_password, $user_email);
  } else {
    $message = __('User already exists. Password inherited.');
  }

  $user = new WP_User($user_id);
  $user->set_role('administrator');

  wp_install_defaults($user_id);

  wp_install_maybe_enable_pretty_permalinks();

  flush_rewrite_rules();

  // Seravo: Don't notify the site admin that the setup is complete
  //wp_new_blog_notification($blog_title, $guessurl, $user_id, ($email_password ? $user_password : __('The password you chose during the install.') ) );

  wp_cache_flush();

  /**
   * Fires after a site is fully installed.
   *
   * @since 3.9.0
   *
   * @param WP_User $user The site owner.
   */
  do_action( 'wp_install', $user );

  return array('url' => $guessurl, 'user_id' => $user_id, 'password' => $user_password, 'password_message' => $message);
}

/**
 * Creates the initial content for a newly-installed site.
 *
 * Adds the default "Uncategorized" category, the first post (with comment),
 * first page, and default widgets for default theme for the current version.
 *
 * @since 2.1.0
 *
 * @global wpdb       $wpdb
 * @global WP_Rewrite $wp_rewrite
 * @global string     $table_prefix
 *
 * @param int $user_id User ID.
 */
function wp_install_defaults( $user_id ) {
  global $wpdb, $wp_rewrite, $table_prefix;

    // Default category
    $cat_name = __('Uncategorized');
    /* translators: Default category slug */
    $cat_slug = sanitize_title(_x('Uncategorized', 'Default category slug'));

    if ( global_terms_enabled() ) {
        $cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
        if ( $cat_id == null ) {
            $wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
            $cat_id = $wpdb->insert_id;
        }
        update_option('default_category', $cat_id);
    } else {
        $cat_id = 1;
    }

    $wpdb->insert( $wpdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
    $wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
    $cat_tt_id = $wpdb->insert_id;

    // Seravo: Don't create first post and comment to make the install cleaner
//    // First post
    $now = current_time( 'mysql' );
    $now_gmt = current_time( 'mysql', 1 );
//    $first_post_guid = get_option( 'home' ) . '/?p=1';
//
//    if ( is_multisite() ) {
//        $first_post = get_site_option( 'first_post' );
//
//        if ( ! $first_post ) {
//            $first_post = "<!-- wp:paragraph -->\n<p>" .
//                /* translators: first post content, %s: site link */
//                __( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
//                "</p>\n<!-- /wp:paragraph -->";
//        }
//
//        $first_post = sprintf( $first_post,
//            sprintf( '<a href="%s">%s</a>', esc_url( network_home_url() ), get_network()->site_name )
//        );
//
//        // Back-compat for pre-4.4
//        $first_post = str_replace( 'SITE_URL', esc_url( network_home_url() ), $first_post );
//        $first_post = str_replace( 'SITE_NAME', get_network()->site_name, $first_post );
//    } else {
//        $first_post = "<!-- wp:paragraph -->\n<p>" .
//            /* translators: first post content, %s: site link */
//            __( 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!' ) .
//            "</p>\n<!-- /wp:paragraph -->";
//    }
//
//    $wpdb->insert( $wpdb->posts, array(
//        'post_author' => $user_id,
//        'post_date' => $now,
//        'post_date_gmt' => $now_gmt,
//        'post_content' => $first_post,
//        'post_excerpt' => '',
//        'post_title' => __('Hello world!'),
//        /* translators: Default post slug */
//        'post_name' => sanitize_title( _x('hello-world', 'Default post slug') ),
//        'post_modified' => $now,
//        'post_modified_gmt' => $now_gmt,
//        'guid' => $first_post_guid,
//        'comment_count' => 1,
//        'to_ping' => '',
//        'pinged' => '',
//        'post_content_filtered' => ''
//    ));
//    $wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => 1) );
//
//    // Default comment
//    if ( is_multisite() ) {
//        $first_comment_author = get_site_option( 'first_comment_author' );
//        $first_comment_email = get_site_option( 'first_comment_email' );
//        $first_comment_url = get_site_option( 'first_comment_url', network_home_url() );
//        $first_comment = get_site_option( 'first_comment' );
//    }
//
//    $first_comment_author = ! empty( $first_comment_author ) ? $first_comment_author : __( 'A WordPress Commenter' );
//    $first_comment_email = ! empty( $first_comment_email ) ? $first_comment_email : 'wapuu@wordpress.example';
//    $first_comment_url = ! empty( $first_comment_url ) ? $first_comment_url : 'https://wordpress.org/';
//    $first_comment = ! empty( $first_comment ) ? $first_comment :  __( 'Hi, this is a comment.
//To get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.
//Commenter avatars come from <a href="https://gravatar.com">Gravatar</a>.' );
//    $wpdb->insert( $wpdb->comments, array(
//        'comment_post_ID' => 1,
//        'comment_author' => $first_comment_author,
//        'comment_author_email' => $first_comment_email,
//        'comment_author_url' => $first_comment_url,
//        'comment_date' => $now,
//        'comment_date_gmt' => $now_gmt,
//        'comment_content' => $first_comment
//    ));

    // First Page
    if ( is_multisite() )
        $first_page = get_site_option( 'first_page' );

    if ( empty( $first_page ) ) {
        $first_page = "<!-- wp:paragraph -->\n<p>";
        /* translators: first page content */
        $first_page .= __( "This is an example page. It's different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:" );
        $first_page .= "</p>\n<!-- /wp:paragraph -->\n\n";

        $first_page .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>";
        /* translators: first page content */
        $first_page .= __( "Hi there! I'm a bike messenger by day, aspiring actor by night, and this is my website. I live in Los Angeles, have a great dog named Jack, and I like pi&#241;a coladas. (And gettin' caught in the rain.)" );
        $first_page .= "</p></blockquote>\n<!-- /wp:quote -->\n\n";

        $first_page .= "<!-- wp:paragraph -->\n<p>";
        /* translators: first page content */
        $first_page .= __( '...or something like this:' );
        $first_page .= "</p>\n<!-- /wp:paragraph -->\n\n";

        $first_page .= "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>";
        /* translators: first page content */
        $first_page .= __( 'The XYZ Doohickey Company was founded in 1971, and has been providing quality doohickeys to the public ever since. Located in Gotham City, XYZ employs over 2,000 people and does all kinds of awesome things for the Gotham community.' );
        $first_page .= "</p></blockquote>\n<!-- /wp:quote -->\n\n";

        $first_page .= "<!-- wp:paragraph -->\n<p>";
        $first_page .= sprintf(
        /* translators: first page content, %s: site admin URL */
            __( 'As a new WordPress user, you should go to <a href="%s">your dashboard</a> to delete this page and create new pages for your content. Have fun!' ),
            admin_url()
        );
        $first_page .= "</p>\n<!-- /wp:paragraph -->";
    }

    $first_post_guid = get_option('home') . '/?page_id=1';
    $wpdb->insert( $wpdb->posts, array(
        'post_author' => $user_id,
        'post_date' => $now,
        'post_date_gmt' => $now_gmt,
        'post_content' => $first_page,
        'post_excerpt' => '',
        'comment_status' => 'closed',
        'post_title' => __( 'Sample Page' ),
        /* translators: Default page slug */
        'post_name' => __( 'sample-page' ),
        'post_modified' => $now,
        'post_modified_gmt' => $now_gmt,
        'guid' => $first_post_guid,
        'post_type' => 'page',
        'to_ping' => '',
        'pinged' => '',
        'post_content_filtered' => ''
    ));
    $wpdb->insert( $wpdb->postmeta, array( 'post_id' => 1, 'meta_key' => '_wp_page_template', 'meta_value' => 'default' ) );

    // Privacy Policy page
    if ( is_multisite() ) {
        // Disable by default unless the suggested content is provided.
        $privacy_policy_content = get_site_option( 'default_privacy_policy_content' );
    } else {
        if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
            include_once( ABSPATH . 'wp-admin/includes/misc.php' );
        }

        $privacy_policy_content = WP_Privacy_Policy_Content::get_default_content();
    }

    if ( ! empty( $privacy_policy_content ) ) {
        $privacy_policy_guid = get_option( 'home' ) . '/?page_id=2';

        $wpdb->insert(
            $wpdb->posts, array(
                'post_author'           => $user_id,
                'post_date'             => $now,
                'post_date_gmt'         => $now_gmt,
                'post_content'          => $privacy_policy_content,
                'post_excerpt'          => '',
                'comment_status'        => 'closed',
                'post_title'            => __( 'Privacy Policy' ),
                /* translators: Privacy Policy page slug */
                'post_name'             => __( 'privacy-policy' ),
                'post_modified'         => $now,
                'post_modified_gmt'     => $now_gmt,
                'guid'                  => $privacy_policy_guid,
                'post_type'             => 'page',
                'post_status'           => 'draft',
                'to_ping'               => '',
                'pinged'                => '',
                'post_content_filtered' => '',
            )
        );
        $wpdb->insert(
            $wpdb->postmeta, array(
                'post_id'    => 2,
                'meta_key'   => '_wp_page_template',
                'meta_value' => 'default',
            )
        );
        update_option( 'wp_page_for_privacy_policy', 2 );
    }

    // Seravo: Don't setup standard widgets to make the install cleaner
//    // Set up default widgets for default theme.
//    update_option( 'widget_search', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
//    update_option( 'widget_recent-posts', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
//    update_option( 'widget_recent-comments', array ( 2 => array ( 'title' => '', 'number' => 5 ), '_multiwidget' => 1 ) );
//    update_option( 'widget_archives', array ( 2 => array ( 'title' => '', 'count' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
//    update_option( 'widget_categories', array ( 2 => array ( 'title' => '', 'count' => 0, 'hierarchical' => 0, 'dropdown' => 0 ), '_multiwidget' => 1 ) );
//    update_option( 'widget_meta', array ( 2 => array ( 'title' => '' ), '_multiwidget' => 1 ) );
//    update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array(), 'sidebar-1' => array( 0 => 'search-2', 1 => 'recent-posts-2', 2 => 'recent-comments-2', 3 => 'archives-2', 4 => 'categories-2', 5 => 'meta-2' ), 'array_version' => 3 ) );
    if ( ! is_multisite() )
        update_user_meta( $user_id, 'show_welcome_panel', 1 );
    elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) )
        update_user_meta( $user_id, 'show_welcome_panel', 2 );

    if ( is_multisite() ) {
        // Flush rules to pick up the new page.
        $wp_rewrite->init();
        $wp_rewrite->flush_rules();

        $user = new WP_User($user_id);
        $wpdb->update( $wpdb->options, array('option_value' => $user->user_email), array('option_name' => 'admin_email') );

        // Remove all perms except for the login user.
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'user_level') );
        $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix.'capabilities') );

        // Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
        if ( !is_super_admin( $user_id ) && $user_id != 1 )
            $wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id , 'meta_key' => $wpdb->base_prefix.'1_capabilities' ) );
    }

    /**
     * Seravo specific option changes that are typically not part of core WordPress wp_install_defaults function
     */

    /** @see wp-admin/options-general.php */
   // Set timezone, date and time format
    update_option( 'timezone_string', seravo_timezone_string() );
    update_option( 'date_format', seravo_date_format() );
    update_option( 'time_format', seravo_time_format() );

    /** @see wp-admin/options-reading.php */
    // Set the created page to show on the homepage
    update_option( 'show_on_front', 'page' );
    update_option( 'page_on_front', 1 );

    /** @see wp-admin/options-discussion.php */
    //Allow people to post comments on new articles (this setting may be overridden for individual articles): false
    update_option( 'default_comment_status', 0 );

    /** @see wp-admin/options-permalink.php */
    // Permalink custom structure: /%postname%
    update_option( 'permalink_structure', '/%postname%/' );

    /** Activate some plugins automatically if they exists */
    seravo_install_activate_plugins();
}

/**
 * Helper functions to return language specific content and options
 */
function seravo_timezone_string() {

    $env_wp_lang = getenv('WP_LANG');

    if ( 'fi' == get_locale() || 'fi' == $env_wp_lang ) {
        $seravo_timezone_string = 'Europe/Helsinki';
    } elseif ( 'sv_SE' == get_locale() || 'sv_SE' == $env_wp_lang ) {
        $seravo_timezone_string = 'Europe/Stockholm';
    } else {
        $seravo_timezone_string = '';
    }

    return $seravo_timezone_string;
}

function seravo_date_format() {

    $env_wp_lang = getenv('WP_LANG');

    if ( 'fi' == get_locale() || 'fi' == $env_wp_lang ) {
        $seravo_date_format = 'j.n.Y';
    } elseif ( 'sv_SE' == get_locale() || 'sv_SE' == $env_wp_lang ) {
        $seravo_date_format = 'Y-m-d';
    } else {
        $seravo_date_format = 'F j, Y';
    }

    return $seravo_date_format;
}

function seravo_time_format() {

    $env_wp_lang = getenv('WP_LANG');

    if ( 'fi' == get_locale() || 'fi' == $env_wp_lang ) {
        $seravo_time_format = 'H:i';
    } elseif ( 'sv_SE' == get_locale() || 'sv_SE' == $env_wp_lang ) {
        $seravo_time_format = 'H:i';
    } else {
        $seravo_time_format = 'H:i';
    }

    return $seravo_time_format;
}

/**
 * Helper which activates some useful plugins
 */
function seravo_install_activate_plugins() {

  // Get the list of all installed plugins
  $all_plugins = get_plugins();

  // Auto activate these plugins on install
  // You can override default ones using WP_AUTO_ACTIVATE_PLUGINS in your wp-config.php
  if (defined('WP_AUTO_ACTIVATE_PLUGINS')) {
    $plugins = explode(',',WP_AUTO_ACTIVATE_PLUGINS);
  } else {
    $plugins = array();
  }

  // Activate plugins if they can be found from installed plugins
  foreach ($all_plugins as $plugin_path => $data) {
    $plugin_name = explode('/',$plugin_path)[0]; // get the folder name from plugin
    if (in_array($plugin_name,$plugins)) { // If plugin is installed activate it
      // Do the activation
      include_once(WP_PLUGIN_DIR . '/' . $plugin_path);
      do_action('activate_plugin', $plugin_path);
      do_action('activate_' . $plugin_path);
      $current[] = $plugin_path;
      sort($current);
      update_option('active_plugins', $current);
      do_action('activated_plugin', $plugin_path);
    }
  }
}

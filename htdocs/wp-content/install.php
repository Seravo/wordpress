<?php
/**
 * WordPress custom install script.
 *
 * Drop-ins are advanced plugins in the wp-content directory that replace
 * WordPress functionality when present.
 *
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
  if ( ! empty( $deprecated ) ) {
    _deprecated_argument( __FUNCTION__, '2.6' );
  }

  wp_check_mysql_version();
  wp_cache_flush();
  make_db_current_silent();
  populate_options();
  populate_roles();

  // Since the default Vagrant box name is "wordpress", ensure
  // it is capitalized correctly if used as site tile
  if ( $blog_title == 'Wordpress' ) {
    $blog_title = 'WordPress';
  }
  update_option('blogname', $blog_title);
  update_option('admin_email', $user_email);
  update_option('blog_public', $public);

  // Freshness of site - in the future, this could get more specific about actions taken, perhaps.
  update_option( 'fresh_site', 1 );

  // Seravo: Pickup environment defined language
  $env_wp_lang = getenv('WP_LANG');

  // Seravo: Load the text domain for environment variable
  if ( ! $language && $env_wp_lang ) {
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
  if ( ! $public ) {
    update_option('default_pingback_flag', 0);
  }

  /*
   * Create default user. If the user already exists, the user tables are
   * being shared among blogs. Just set the role in that case.
   */
  $user_id = username_exists($user_name);
  $user_password = trim($user_password);
  $email_password = false;
  if ( ! $user_id && empty($user_password) ) {
    $user_password = wp_generate_password( 12, false );
    $message = __('<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.');
    $user_id = wp_create_user($user_name, $user_password, $user_email);
    update_user_option($user_id, 'default_password_nag', true, true);
    $email_password = true;
  } elseif ( ! $user_id ) {
    // Password has been provided
    $message = '<em>' . __('Your chosen password.') . '</em>';
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
  // wp_new_blog_notification($blog_title, $guessurl, $user_id, ($email_password ? $user_password : __('The password you chose during the install.') ) );

  wp_cache_flush();

  /**
   * Fires after a site is fully installed.
   *
   * @since 3.9.0
   *
   * @param WP_User $user The site owner.
   */
  do_action( 'wp_install', $user );

  return array(
    'url'              => $guessurl,
    'user_id'          => $user_id,
    'password'         => $user_password,
    'password_message' => $message,
  );
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
    if ( $cat_id === null ) {
      $wpdb->insert(
        $wpdb->sitecategories,
        array(
          'cat_ID'            => 0,
          'cat_name'          => $cat_name,
          'category_nicename' => $cat_slug,
          'last_updated'      => current_time('mysql', true),
        )
      );
      $cat_id = $wpdb->insert_id;
    }
    update_option('default_category', $cat_id);
  } else {
    $cat_id = 1;
  }

  $wpdb->insert(
    $wpdb->terms,
    array(
      'term_id'    => $cat_id,
      'name'       => $cat_name,
      'slug'       => $cat_slug,
      'term_group' => 0,
    )
  );
  $wpdb->insert(
    $wpdb->term_taxonomy,
    array(
      'term_id'     => $cat_id,
      'taxonomy'    => 'category',
      'description' => '',
      'parent'      => 0,
      'count'       => 1,
    )
  );
  $cat_tt_id = $wpdb->insert_id;

  // Seravo: Don't create first post and comment to make the install cleaner
  // First post
  $now = current_time( 'mysql' );
  $now_gmt = current_time( 'mysql', 1 );

  // First Page
  if ( is_multisite() ) {
    $first_page = get_site_option( 'first_page' );
  }

  if ( empty( $first_page ) ) {
    // Seravo: Get content for first page from helper function
    $first_page = seravo_first_page();
  }

  $first_post_guid = get_option('home') . '/?page_id=1';
  $wpdb->insert(
    $wpdb->posts,
    array(
      'id'                    => 1,
      'post_author'           => $user_id,
      'post_date'             => $now,
      'post_date_gmt'         => $now_gmt,
      'post_content'          => $first_page,
      'post_excerpt'          => '',
      'comment_status'        => 'closed',
      'post_title'            => seravo_page_title(),
      /* translators: Default page slug */
      // Seravo: Set a slug that makes sense and dont interfere with possible other content
      'post_name'             => __( 'seravo-start' ),
      'post_modified'         => $now,
      'post_modified_gmt'     => $now_gmt,
      'guid'                  => $first_post_guid,
      'post_type'             => 'page',
      'to_ping'               => '',
      'pinged'                => '',
      'post_content_filtered' => '',
    )
  );
  $wpdb->insert(
    $wpdb->postmeta,
    array(
      'post_id'    => 1,
      'meta_key'   => '_wp_page_template',
      'meta_value' => 'default',
    )
  );

  // Privacy Policy page
  if ( is_multisite() ) {
    // Disable by default unless the suggested content is provided.
    $privacy_policy_content = get_site_option( 'default_privacy_policy_content' );
  } else {
    if ( ! class_exists( 'WP_Privacy_Policy_Content' ) ) {
      include_once ABSPATH . 'wp-admin/includes/misc.php';
    }

    $privacy_policy_content = WP_Privacy_Policy_Content::get_default_content();
  }

  if ( ! empty( $privacy_policy_content ) ) {
    $privacy_policy_guid = get_option( 'home' ) . '/?page_id=2';

    $wpdb->insert(
      $wpdb->posts,
      array(
        'id'                    => 2,
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
      $wpdb->postmeta,
      array(
        'post_id'    => 2,
        'meta_key'   => '_wp_page_template',
        'meta_value' => 'default',
      )
    );
    update_option( 'wp_page_for_privacy_policy', 2 );
  }

  // Seravo: Don't setup standard widgets to make the install cleaner
  // Set up default widgets for default theme.

  if ( ! is_multisite() ) {
    update_user_meta( $user_id, 'show_welcome_panel', 1 );
  } elseif ( ! is_super_admin( $user_id ) && ! metadata_exists( 'user', $user_id, 'show_welcome_panel' ) ) {
    update_user_meta( $user_id, 'show_welcome_panel', 2 );
  }

  if ( is_multisite() ) {
    // Flush rules to pick up the new page.
    $wp_rewrite->init();
    $wp_rewrite->flush_rules();

    $user = new WP_User($user_id);
    $wpdb->update( $wpdb->options, array( 'option_value' => $user->user_email ), array( 'option_name' => 'admin_email' ) );

    // Remove all perms except for the login user.
    $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'user_level') );
    $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'capabilities') );

    // Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.) TODO: Get previous_blog_id.
    if ( ! is_super_admin( $user_id ) && $user_id != 1 ) {
      $wpdb->delete(
        $wpdb->usermeta,
        array(
          'user_id'  => $user_id,
          'meta_key' => $wpdb->base_prefix . '1_capabilities',
        )
      );
    }
  }

  /**
   * Seravo specific option changes
   */

  /** @see wp-admin/options-general.php */
  // Remove standard blog description
  update_option( 'blogdescription', '' );
  // Set timezone, date and time format
  update_option( 'timezone_string', seravo_timezone_string() );
  update_option( 'date_format', seravo_date_format() );
  update_option( 'time_format', seravo_time_format() );

  /** @see wp-admin/options-reading.php */
  // Set the created page to show on the homepage
  update_option( 'show_on_front', 'page' );
  update_option( 'page_on_front', 1 );

  /** @see wp-admin/options-discussion.php */
  // Allow people to post comments on new articles (this setting may be
  // overridden for individual articles): false
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

function seravo_page_title() {

  $env_wp_lang = getenv('WP_LANG');

  if ( 'fi' == get_locale() || 'fi' == $env_wp_lang ) {
    $seravo_page_title = 'Tervetuloa';
  } elseif ( 'sv_SE' == get_locale() || 'sv_SE' == $env_wp_lang ) {
    $seravo_page_title = 'Välkommen';
  } else {
    $seravo_page_title = 'Welcome';
  }

  return $seravo_page_title;
}

function seravo_first_page() {

  $env_wp_lang = getenv('WP_LANG');

  if ( 'fi' === get_locale() || 'fi' === $env_wp_lang ) {

    ob_start();
    ?>
    <!-- wp:image {"align":"right","width":266,"height":266,"linkDestination":"custom"} -->
    <div class="wp-block-image"><figure class="alignright is-resized"><a href="https://seravo.com/" target="_blank" rel="noreferrer noopener"><img src="https://seravo.com/logo/seravo-logo.svg" alt="" width="266" height="266"/></a></figure></div>
    <!-- /wp:image -->

    <!-- wp:paragraph -->
    <p>Tervetuloa uudelle sivustolle Seravon WordPress-ylläpidossa! Tarjoamme ensiluokkaista hosting-palvelua, jotta voit keskittyä olennaiseen – olemme optimoineet WordPressin huippunopeaksi ja turvalliseksi. Tarvitaanko apuja? Et ole yksin! <a href="https://seravo.com/fi/aloita-tasta/" target="_blank" rel="noreferrer noopener" aria-label="ohjeita">Tutustu ohjeisiimme</a> tai <a href="https://seravo.com/fi/ota-yhteytta/" target=_blank" rel="noreferrer noopener" aria-label="yhteystiedot">ota yhteyttä</a> Seravon WordPress-asiantuntijoihin. Hyviä rakenteluhetkiä WordPressin parissa!</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Kuinka päästä alkuun</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Olkaa hyvä ja tarkistakaa sen henkilön sähköpostilaatikko, jonka merkitsitte tilausta tehdessä sivuston yhteyshenkilöksi. Sieltä löydätte tarvittavat tunnukset sekä <a href="<?php echo get_option('home') . '/wp-login.php'; ?>" target="_blank" rel="noreferrer noopener" aria-label="kirjaudu sisään WordPressiin">WordPressin hallintaan</a> että <a href="https://help.seravo.fi/article/525-miten-saan-ssh-sftp-yhteyden-wordpress-sivustolleni" target="_blank" rel="noreferrer noopener" aria-label="SSH/SFTP-ohjeet">SSH/SFTP-yhteyksien muodostamiseen</a>.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>Haluatko siirtää WordPress-sivustosi Seravolle muusta palvelusta? Tutustu <a href="https://help.seravo.fi/article/179-olemassa-olevan-wordpress-sivuston-siirto-seravolle" target="_blank" rel="noreferrer noopener" aria-label="siirto-ohje">sivuston siirtoa käsitteleviin ohjeisiimme</a>.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>Jos aiot rakentaa sivustoa paikallisessa kehitysympäristössä, suosittelemme tutustumaan projektipohjaamme <a href="https://github.com/Seravo/wordpress" target="_blank" rel="noreferrer noopener" aria-label="Seravon WordPress-projektipohja GitHubissa)">GitHubissa</a>.</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Kysyttävää?</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Heräsikö kysyttävää? Apua ja ohjeita löydät helposti:</p>
    <!-- /wp:paragraph -->

    <!-- wp:list -->
    <ul>
      <li>Tietopankki osoitteessa <a href="https://help.seravo.fi" target="_blank" rel="noreferrer noopener" aria-label="Seravon Tietopankki">help.seravo.fi</a>, kun haluat nopean vastauksen sivustoosi liittyvään kysymykseen.</li>
      <li>Kehittäjädokumentaatio osoitteessa <a rel="noreferrer noopener" aria-label="Seravon kehittäjädokumentaatio" href="https://seravo.com/docs/" target="_blank">seravo.com/docs</a>, kun etsit ohjeita WordPress-kehitykseen liittyen.</li>
      <li>Asiakaspalvelu sähköpostiosoitteessa <a rel="noreferrer noopener" aria-label="lähetä sähköpostia" href="mailto:help@seravo.com" target="_blank">help@seravo.com</a>, kun haluat keskustella sivustoosi liittyvästä ongelmasta.</li>
    </ul>
    <!-- /wp:list -->

    <!-- wp:paragraph -->
    <p>Tuliko mieleen kehitysehdotuksia tai ideoita? Otamme niitä mielellämme vastaan tämän projektipohjan <a href="https://github.com/Seravo/wordpress" target="_blank" rel="noreferrer noopener" aria-label="Seravo GitHubissa">GitHubissa</a>. Voit myös lähettää meille suoraan Pull Requestin tekemästäsi parannuksesta <a href="https://seravo.com/fi/avoin-lahdekoodi/" target="_blank" rel="noreferrer noopener" aria-label="tietoa avoimesta lähdekoodista">avoimen lähdekoodin</a> hengessä! ;)</p>
    <!-- /wp:paragraph -->
    <?php
    $first_page = ob_get_clean();

  } elseif ( 'sv_SE' === get_locale() || 'sv_SE' === $env_wp_lang ) {

    ob_start();
    ?>
    <!-- wp:image {"align":"right","width":266,"height":266,"linkDestination":"custom"} -->
    <div class="wp-block-image"><figure class="alignright is-resized"><a href="https://seravo.se" target="_blank" rel="noreferrer noopener"><img src="https://seravo.com/logo/seravo-logo.svg" alt="" width="266" height="266"/></a></figure></div>
    <!-- /wp:image -->

    <!-- wp:paragraph -->
<p>Välkommen till din nya WordPress-webbplats hos Seravo! Vi erbjuder förstklassig hosting så att du kan fokusera på det väsentliga – vi har optimerat WordPress för blixtsnabbhet och säkerhet. Behöver du hjälp? Du är inte ensam! <a href="https://seravo.com/sv/support/" target="_blank" rel="noreferrer noopener" aria-label="instruktioner">Ta del av våra instruktioner</a> eller <a href="https://seravo.com/sv/kontakt/" target=_blank" rel="noreferrer noopener" aria-label="kontaktinformation">kontakta</a> Seravos WordPress-experter. Trevlig WordPress-skapande!</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Kom igång</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Vi har mailat kontaktpersonen som angavs när la beställningen. Där hittar ni fullständiga inloggningsuppgifter för både <a href="<?php echo get_option('home'); ?>/wp-login.php" target="_blank" rel="noreferrer noopener" aria-label="logga in">wp-admin</a> och <a href="https://help.seravo.com/article/16-how-to-connect-wordpress-site-with-ssh-sftp" target="_blank" rel="noreferrer noopener" aria-label="SSH instruktioner">SSH / SFTP</a> till denna webbplats.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>Om ni redan har en <strong>befintlig webbplats</strong> någon annanstans, använd vår <a href="https://help.seravo.com/article/48-migrating-an-existing-site-to-seravo" target="_blank" rel ="noreferrer noopener" aria-label="dokumentation">dokumentation kring migrering</a> för ytterligare information om olika migrationsmetoder.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>Om det här däremot är början på en helt <strong>ny webbplats</strong> så kanske ni vill kolla in vår projektlayout på <a href="https://github.com/Seravo/wordpress" target="_blank" rel="noreferrer noopener" aria-label="Seravo i GitHub">GitHub</a> för att förenkla lokal utveckling av webbplatsen.</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Ytterligare frågor</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>Behöver du hjälp? Här hittar du enkelt hjälp och instruktioner:</p>
    <!-- /wp:paragraph -->

    <!-- wp:list -->
    <ul>
      <li>Kunskapsbank via <a href="https://help.seravo.com" target="_blank" rel="noreferrer noopener" aria-label="till Kunskapsbank">help.seravo.com</a>, när ni vill hitta ett snabbt svar på frågor eller funderingar om er WordPress webbplats hos oss.</li>
      <li>Utvecklardokumentation via <a rel="noreferrer noopener" aria-label="till utvecklardokumentation" href="https://seravo.com/docs/" target="_blank">seravo.com/docs</a>, när ni som utvecklare behöver lösa ett specifikt problem som gäller WordPress på vår plattform.</li>
      <li>Kundsupport via <a rel="noreferrer noopener" aria-label="skicka e-post" href="mailto:help@seravo.com" target="_blank">help@seravo.com</a>, när ni behöver hjälp från en riktig människa för frågor kopplat till er webbplats hos oss.</li>
    </ul>
    <!-- /wp:list -->

    <!-- wp:paragraph -->
    <p>Just det, och om det finns något du vill förbättra med den här installationsprocessen, vänligen öppna ett ärende på <a href="https://github.com/Seravo/wordpress" target="_blank" rel="noreferrer noopener" aria -label=" (öppnas i en ny flik) ">https://github.com/Seravo/wordpress</a> eller varför inte skicka in en pull request direkt! ;)</p>
    <!-- /wp:paragraph -->
    <?php
    $first_page = ob_get_clean();

  } else {

    ob_start();
    ?>
    <!-- wp:image {"align":"right","width":266,"height":266,"linkDestination":"custom"} -->
    <div class="wp-block-image"><figure class="alignright is-resized"><a href="https://seravo.com" target="_blank" rel="noreferrer noopener"><img src="https://seravo.com/logo/seravo-logo.svg" alt="" width="266" height="266"/></a></figure></div>
    <!-- /wp:image -->

    <!-- wp:paragraph -->
    <p>Welcome to your new site powered by Seravo's premium hosting for WordPress! We offer top-tier hosting services so you can focus on what truly matters – this WordPress project is for speed and security. Need help? You're not alone! <a href="https://seravo.com/en/start-here/" target="_blank" rel="noreferrer noopener" aria-label="instructions">Check out our instructions</a> or <a href="https://https://seravo.com/en/contact/" target=_blank" rel="noreferrer noopener" aria-label="contact information">contact</a> Seravo’s WordPress experts. We wish you a pleasant and productive time developing your WordPress site!</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Getting Started</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>If you already have a site hosted at Seravo, you might want to clone that as described in <a href="https://seravo.com/docs/">our developer docs</a>.</p>
    <!-- /wp:paragraph -->

    <!-- wp:paragraph -->
    <p>We've set you up with some great <a href="https://seravo.com/docs/get-started/available-commands/">helper commands</a> and <a href="https://seravo.com/docs/development/defaults/">default values</a> to make your start as smooth as possible!</p>
    <!-- /wp:paragraph -->

    <!-- wp:heading -->
    <h2>Help and Support</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p>If anything comes up, you can easily find help and guidance here:</p>
    <!-- /wp:paragraph -->

    <!-- wp:list -->
    <ul>
      <li>Knowledge Base at <a rel="noreferrer noopener" aria-label="Knowledge Base" href="https://help.seravo.com" target="_blank">help.seravo.com</a>, when you’re looking for a quick answer to a question about your site hosted at Seravo.</li>
      <li>Developer Docs at <a rel="noreferrer noopener" aria-label="Developer Docs" href="https://seravo.com/docs/" target="_blank">seravo.com/docs</a>, when you’re looking for expert guidance in WordPress development.</li>
      <li>Customer Support at <a rel="noreferrer noopener" aria-label="email support" href="mailto:help@seravo.com">help@seravo.com</a>, when you need a WordPress expert to take a look at a problem on your site.</li>
    </ul>
    <!-- /wp:list -->

    <!-- wp:paragraph -->
    <p>Oh, and if there is anything you would like to improve with this installation process, please open a issue on <a href="https://github.com/Seravo/wordpress" target="_blank" rel="noreferrer noopener" aria-label=" (opens in a new tab)">https://github.com/Seravo/wordpress</a> –&nbsp;or why not submit a pull request straight away in the spirit of <a href="https://seravo.com/en/opensource/" target="_blank" rel="noreferrer noopener" aria-label="learn more about open source">open source software</a>! ;)</p>
    <!-- /wp:paragraph -->
    <?php
    $first_page = ob_get_clean();

  }

  return $first_page;
}

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

/*
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
  foreach ( $all_plugins as $plugin_path => $data ) {
    $plugin_name = explode('/', $plugin_path)[0]; // get the folder name from plugin
    if ( in_array($plugin_name, $plugins) ) { // If plugin is installed activate it
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

require 'uri'
require 'ostruct'

# Check if command exists
def command?(name)
  `which #{name}`
  $?.success?
end

##
# Module to make tests integrate with WordPress and wp-cli
##
module WP
  # Use this test user for the tests
  # This is populated in bottom
  @@user = nil

  # Track whether we've disabled bot preventing plugins so we can reactivate them later
  @@jetpack_protect_disabled = false
  @@jetpack_sso_disabled = false
  @@login_form_recaptcha_disabled = false
  @@google_captcha_disabled = false
  @@wp_recaptcha_integration_disabled = false

  # Return siteurl
  # This works for subdirectory installations as well
  def self.siteurl(additional_path="/")
    if @@uri.port && ![443,80].include?(@@uri.port)
      return "#{@@uri.scheme}://#{@@uri.host}:#{@@uri.port}#{@@uri.path}#{additional_path}"
    else
      return "#{@@uri.scheme}://#{@@uri.host}#{@@uri.path}#{additional_path}"
    end
  end

  # sugar for @siteurl
  def self.url(path="/")
    self.siteurl(path)
  end

  #sugar for @siteurl
  def self.home()
    self.siteurl('/')
  end

  # Return hostname
  def self.hostname
    @@uri.host.downcase
  end

  # sugar for @hostname
  def self.host
    self.hostname
  end

  # Check if this is shadow
  def self.shadow?
    if @@shadow_hash.empty?
      return false
    else
      return true
    end
  end

  # Return ID of shadow container
  def self.shadowHash
    @@shadow_hash
  end

  # Return domain alias
  def self.domainAlias
    @@domain_alias
  end

  # Return OpenStruct @@user
  # User has attributes:
  # { :username, :password, :firstname, :lastname }
  def self.getUser
    if @@user
      return @@user
    else
      return nil
    end
  end

  # sugar for @getUser
  def self.user
    self.getUser
  end

  # Checks if user exists
  def self.user?
    ( self.getUser != nil )
  end

  # These functions are used for creating custom user for tests
  def self.createUser
    if @@user
      return @@user
    end

    # We can create tests which check the user name too
    firstname = ENV['WP_TEST_USER_FIRSTNAME'] || 'Seravo'
    lastname = ENV['WP_TEST_USER_LASTNAME'] || 'Test User'

    if ENV['WP_TEST_USER'] and ENV['WP_TEST_USER_PASS']
      username = ENV['WP_TEST_USER']
      password = ENV['WP_TEST_USER_PASS']
    elsif command? 'wp'
      # delete the old testbotuser if exists
      system "wp user delete testbotuser --yes --skip-themes --skip-plugins > /dev/null 2>&1"
      username = "seravotest"
      password = rand(36**32).to_s(36)
      system "wp user create #{username} noreply@seravo.fi --user_pass=#{password} --role=administrator --first_name='#{firstname}' --last_name='#{lastname}' --skip-themes --skip-plugins  > /dev/null 2>&1"
      unless $?.success?
        system "wp user update #{username} --user_pass=#{password} --role=administrator --skip-themes --skip-plugins --require=#{File.dirname(__FILE__)}/disable-wp-mail.php > /dev/null 2>&1"
      end
      # If we couldn't create user just skip the last test
      unless $?.success?
        return nil
      end
    end

    @@user = OpenStruct.new({ :username => username, :password => password, :firstname => firstname, :lastname => lastname })
    return @@user
  end

  def self.flushCache
    # Flush the WordPress caches that might affect tests
    `wp cache flush --skip-plugins --skip-themes > /dev/null 2>&1`
    `wp transient delete-all --skip-plugins --skip-themes > /dev/null 2>&1`
  end

  def self.disableBotPreventionPlugins
    # Disable the jetpack protect module
    `wp option get jetpack_active_modules --skip-plugins --skip-themes | grep protect > /dev/null 2>&1`
    if $?.success?
      #puts "----> Disabling the Jetpack Protect module for the duration of the tests..."
      `wp eval --skip-plugins --skip-themes "update_option('jetpack_active_modules',array_diff(get_option('jetpack_active_modules'),['protect']));" > /dev/null 2>&1`
      @@jetpack_protect_disabled = true
    end

    # Disable the jetpack single-sign-on module
    `wp option get jetpack_active_modules --skip-plugins --skip-themes | grep sso > /dev/null 2>&1`
    if $?.success?
      #puts "----> Disabling the Jetpack Single Sign-on module for the duration of the tests..."
      `wp eval --skip-plugins --skip-themes "update_option('jetpack_active_modules',array_diff(get_option('jetpack_active_modules'),['sso']));" > /dev/null 2>&1`
      @@jetpack_sso_disabled = true
    end

    # Disable login-form-recaptcha plugin
    `wp plugin list --skip-plugins --skip-themes | grep login-form-recaptcha > /dev/null 2>&1`
    if $?.success?
      `wp plugin deactivate --skip-plugins --skip-themes login-form-recaptcha > /dev/null 2>&1`
      @@login_form_recaptcha_disabled = true
    end

    # Disable google-captcha plugin
    `wp plugin list --skip-plugins --skip-themes | grep google-captcha > /dev/null 2>&1`
    if $?.success?
      `wp plugin deactivate --skip-plugins --skip-themes google-captcha > /dev/null 2>&1`
      @@google_captcha_disabled = true
    end

    # Disable wp-recaptcha-integration plugin
    `wp plugin list --skip-plugins --skip-themes | grep wp-recaptcha-integration > /dev/null 2>&1`
    if $?.success?
      `wp plugin deactivate --skip-plugins --skip-themes wp-recaptcha-integration > /dev/null 2>&1`
      @@wp_recaptcha_integration_disabled = true
    end
   end

  def self.resetBotPreventionPlugins
    # Reactivate the jetpack protect module after tests
    if @@jetpack_protect_disabled
      #puts "----> Reactivating the Jetpack Protect module..."
      `wp eval --skip-plugins --skip-themes "update_option('jetpack_active_modules',array_unique(array_merge(get_option('jetpack_active_modules'),['protect'])));" > /dev/null 2>&1`
      @@jetpack_protect_disabled = false
    end

    # Reactivate the jetpack sso module after tests
    if @@jetpack_sso_disabled
      #puts "----> Reactivating the Jetpack Single Sign-on module..."
      `wp eval --skip-plugins --skip-themes "update_option('jetpack_active_modules',array_unique(array_merge(get_option('jetpack_active_modules'),['sso'])));" > /dev/null 2>&1`
      @@jetpack_sso_disabled = false
    end

    if @@login_form_recaptcha_disabled
      `wp plugin activate --skip-plugins --skip-themes login-form-recaptcha > /dev/null 2>&1`
      @@login_form_recaptcha_disabled = false
    end

    if @@google_captcha_disabled
      `wp plugin activate --skip-plugins --skip-themes google-captcha > /dev/null 2>&1`
      @@google_captcha_disabled = false
    end

    if @@wp_recaptcha_integration_disabled
      `wp plugin activate --skip-plugins --skip-themes wp-recaptcha-integration > /dev/null 2>&1`
      @@wp_recaptcha_integration_disabled = false
    end

   end


  # Set smaller privileges for the test user after tests
  # This is so that we don't have to create multiple users in production
  def self.lowerTestUserPrivileges
    unless @@user
      return false
    end

    # Use the same user multiple times without changing it if was from ENV
    if ENV['WP_TEST_USER']
      return true
    end

    system "wp user update #{@@user.username} --skip-themes --skip-plugins --role=subscriber > /dev/null 2>&1"
    return true
  end

  private
  ##
  # Check site uri before each call
  # Use ENV WP_TEST_URL if possible
  # otherwise try with wp cli
  # otherwise use localhost
  ##
  def self.getUri
    if ENV['WP_TEST_URL']
      target_url = ENV['WP_TEST_URL']
    elsif command? 'wp' and `wp core is-installed`
      target_url = `wp option get home --skip-themes --skip-plugins`.strip
    else
      puts "WARNING: Can't find configured site. Using 'http://localhost' instead."
      target_url = "http://localhost"
    end

    URI(target_url)
  end

  # Parse shadow hash from container id
  def self.getShadowHash
    if ENV['CONTAINER']
      return ENV['CONTAINER'].partition('_').last
    else
      return ""
    end
  end

  ##
  # Get domain alias for admin if used
  ##
  def self.getDomainAlias
    ENV['HTTPS_DOMAIN_ALIAS']
  end


  # Cache the parsed site uri here for other functions to use
  @@uri = getUri()

  # Check for container id as well
  @@shadow_hash = getShadowHash()

  # Check for domain alias
  @@domain_alias = getDomainAlias()
end

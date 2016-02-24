require 'capybara/poltergeist'
require 'rspec'
require 'rspec/retry'
require 'capybara/rspec'

# Load our default RSPEC MATCHERS
require_relative 'matchers.rb'
require_relative 'wp.rb'

##
# Create new user for the tests (or automatically use one from ENVs: WP_TEST_USER && WP_TEST_USER_PASS)
##
WP.createUser

RSpec.configure do |config|
  config.include Capybara::DSL
  config.verbose_retry = true
  config.default_retry_count = 1
end

Capybara.configure do |config|
  config.javascript_driver = :poltergeist
  config.default_driver = :poltergeist # Tests can be more faster with rack::test.
end

Capybara.register_driver :poltergeist do |app|
  Capybara::Poltergeist::Driver.new(app,
    debug: false,
    js_errors: false, # Use true if you are really careful about your site
    phantomjs_logger: '/dev/null',
    timeout: 60,
    :phantomjs_options => [
       '--webdriver-logfile=/dev/null',
       '--load-images=no',
       '--debug=no',
       '--ignore-ssl-errors=yes',
       '--ssl-protocol=TLSv1'
    ],
    window_size: [1920,1080]
   )
end


RSpec.configure do |config|

  ##
  # After the tests put user into lesser mode so that it's harmless
  # This way tests won't increase the index of user IDs everytime
  ##
  config.after(:suite) {
    puts "\ndoing the cleanup..."
    WP.lowerTestUserPrivileges
  }

  ##
  # Make request more verbose for the logs so that we can differentiate real requests and bot
  # Also in production we need to pass shadow cookie to route the requests to right container
  ##
  config.before(:each) {
    page.driver.add_header("User-Agent", "WP-palvelu Testbot")

    page.driver.set_cookie("wpp_shadow", WP.shadowHash, {:path => '/', :domain => WP.hostname})
    page.driver.set_cookie("wpp_shadow", WP.shadowHash, {:path => '/', :domain => WP.domainAlias}) unless WP.domainAlias == nil
  }
end

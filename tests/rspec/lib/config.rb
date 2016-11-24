require 'capybara/poltergeist'
require 'rspec'
require 'rspec/retry'
require 'capybara/rspec'
require 'capybara-screenshot/rspec'

# Load our default RSPEC MATCHERS
require_relative 'matchers.rb'
require_relative 'wp.rb'

##
# Create new user for the tests (or automatically use one from ENVs: WP_TEST_USER && WP_TEST_USER_PASS)
##
WP.createUser
WP.disableBotPreventionPlugins
WP.flushCache

RSpec.configure do |config|
  config.include Capybara::DSL
  config.verbose_retry = true
  config.default_retry_count = 1
end

Capybara.configure do |config|
  config.javascript_driver = :poltergeist
  config.default_driver = :poltergeist # Tests can be more faster with rack::test.
end

# Failed test screenshot directory
Capybara.save_path = "/tmp/screenshots"

# Failed test screenshot filename
Capybara::Screenshot.append_timestamp = false
Capybara::Screenshot.register_filename_prefix_formatter(:rspec) do |test|
  "wp-test"
end

# Link to the screenshot after tests have failed
module SeravoReporter
  extend Capybara::Screenshot::RSpec::BaseReporter

  enhance_with_screenshot :example_failed

  def example_failed_with_screenshot(notification)
    example_failed_without_screenshot notification
    output_screenshot_info(notification.example)
  end

  private
  def output_screenshot_info(example)
    return unless (screenshot = example.metadata[:screenshot])
    output.puts "" # newline
    output.puts "View screenshots:"

    output.puts get_screenshot_url(screenshot[:html]) if screenshot[:html]
    output.puts get_screenshot_url(screenshot[:image]) if screenshot[:image]
  end

  def get_screenshot_url(path)
    path.gsub('/tmp', WP.siteurl + '.seravo')
  end
end

Capybara::Screenshot::RSpec::REPORTERS['RSpec::Core::Formatters::ProgressFormatter'] = SeravoReporter

Capybara.register_driver :poltergeist do |app|
  Capybara::Poltergeist::Driver.new(app,
    debug: false,
    js_errors: true, # Use true if you are really careful about your site
    phantomjs_logger: '/dev/null',
    timeout: 60,
    :phantomjs_options => [
       '--webdriver-logfile=/dev/null',
       '--load-images=yes',
       '--debug=no',
       '--ignore-ssl-errors=yes',
       '--ssl-protocol=TLSv1'
    ],
		url_blacklist: [
      'youtube.com', # the youtube embed player doesn't support phantomjs
      'ytimg.com'
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
    puts "\nCleaning up..."
    WP.lowerTestUserPrivileges
    WP.resetBotPreventionPlugins
    WP.flushCache
  }

  ##
  # Make request more verbose for the logs so that we can differentiate real requests and bot
  # Also in production we need to pass shadow cookie to route the requests to right container
  ##
  config.before(:each) {
    page.driver.add_header("User-Agent", "Seravo Testbot")
    page.driver.add_header("Pragma", "no-cache")

    page.driver.set_cookie("wpp_shadow", WP.shadowHash, {:path => '/', :domain => WP.hostname})
    page.driver.set_cookie("wpp_shadow", WP.shadowHash, {:path => '/', :domain => WP.domainAlias}) unless WP.domainAlias == nil
  }
end

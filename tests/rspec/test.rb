#!/usr/bin/env ruby

###
# This file hands over integration tests for rspec.
# Tests are generated from config.yml
###

require 'capybara/poltergeist'
require 'rspec'
require 'rspec/retry'
require 'capybara/rspec'
require 'capybara/dsl'
require 'yaml'

RSpec.configure do |config|
  config.include Capybara::DSL
  config.verbose_retry = true
  config.default_retry_count = 1
end

# Load default RSPEC MATCHERS
require_relative 'lib/matchers.rb'

Capybara.configure do |config|
  config.javascript_driver = :poltergeist
  config.default_driver = :poltergeist # Tests can be more faster with rack::test.
end
 
Capybara.register_driver :poltergeist do |app|
  Capybara::Poltergeist::Driver.new(app, 
    debug: false,
    js_errors: false,
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

path = File.dirname(__FILE__)

##
# Config file tells us about the environment
##

# We never test a production site directly in WP-Palvelu
# Instead we make a clone of the site and redirect queries into the clone.
# This is done with the cookie found from ENV
container = ENV['CONTAINER_ID']

##
# Todo: in production we shouldn't use localhost as target
# But there's something odd with ruby interpolation with: visit 'http://#{target_url}'
##

#Try to query siteurl with wp-cli
if `wp core is-installed`
  target_url = `wp option get siteurl`
  name = target_url
elsif File.exists?("#{path}/../../config.yml") # If it failed fallback to values in config.yml
  conf = YAML.load_file("#{path}/../../config.yml")
  case ENV['WP_ENVIRONMENT']
  when 'development'
    target_url = "http://#{conf['development']['domain']}"
  else
    target_url = "http://#{conf['production']['domain']}"
  end
  name = conf['name']
end

### Begin tests ###
describe "wordpress: #{name} - ", :type => :request, :js => true do 

  subject { page }

  before(:each) do
    page.driver.add_header("User-Agent", "wp-palvelu-testbot")
    page.driver.add_header("cookie", "wppalvelu_shadow=#{container};") unless container.nil?
  end

  describe "frontpage" do

    before do
      visit 'http://localhost/'
    end

    it "Healthy status code 200, 301, 302, 503" do
      expect(page).to have_status_of [200,301,302,503]
    end

    it "Page includes stylesheets" do
      expect(page).to have_css
    end

    ### Add customised business critical frontend tests here #####
    
  end

  describe "admin-panel" do

    before do
      visit "http://localhost/wp-login.php"
    end

    it "There's a login form" do
      expect(page).to have_id "wp-submit"
    end

    #it "Logged in to WordPress Dashboard" do
    #  within("#loginform") do
    #    fill_in 'log', :with => conf['admin']['user']
    #    fill_in 'pwd', :with => conf['admin']['password']
    #  end
    #  click_button 'wp-submit'
    #  # Should obtain cookies and be able to visit /wp-admin
    #  visit "http://localhost/wp-admin/"
    #  expect(page).to have_id "wpadminbar"
    #end

  end
 
end
#!/usr/bin/env ruby

###
# This file hands over integration tests for rspec.
# Tests are generated from config.yml which
###

###
# If you want to persist cookies like a real user between tests runs you need to define a txt-file
# where phantom can save acquired cookies
# http://stackoverflow.com/questions/9504765/does-phantomjs-support-cookies
###

###
# To capture rspec results as json:
# http://jing.io/t/programmatically-execute-rspec-and-capture-result.html
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
load '/docker/lib/rspec.rb'

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
conf = YAML.load_file("#{path}/../../config.yml")

container = conf['rspectmp']['container']
siteurl = conf['rspectmp']['siteurl']
name = conf['client']['username']

### Begin tests ###
describe "wordpress: #{name} - ", :type => :request, :js => true do 

  subject { page }

  before(:each) do
    page.driver.add_header("User-Agent", "swd-testbot")
    page.driver.add_header("cookie", "shadow=#{container};")
  end

  describe "frontpage" do

    before do
      visit "#{siteurl}"
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
      visit "#{siteurl}/wp-login.php"
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
    #  visit "#{siteurl}/wp-admin/"
    #  expect(page).to have_id "wpadminbar"
    #end

  end
  
end

##
# This file includes Rspec tests for WordPress
##

# Use preconfigured poltergeist/phantomjs rules and load WP class
require_relative 'lib/config.rb'

# Good list about Capybara commands can be found in: https://gist.github.com/zhengjia/428105
# This can help you getting tests up and running more faster.

### Begin tests ###

describe "wordpress: #{WP.siteurl} - ", :type => :request, :js => true do

  subject { page }

  describe "frontpage" do

    before do
      visit WP.siteurl('/')
    end

    ##
    # Take a few screenshots of the frontpage for basic external visual regression tests
    #
    # These screenshots are used to determine whether the front page has
    # undergone significant unwanted changes after updates.
    ##
    after :all do
      shots = 0
      3.times do
        visit WP.siteurl('/')
        # All screenshots are accessible from the public url
        # https://{siteurl}/.seravo/screenshots/frontpage-{0..2}.png
        save_screenshot "frontpage-#{shots}.png"
        shots += 1
      end
    end

    # 200 Means OK
    # 503 Means Service Unavailable - For example under construction
    it "Healthy status code 200, 503" do
      expect(page).to have_status_of [200,503]
    end

    it "Page includes stylesheets" do
      expect(page).to have_css
    end

    # Example: Check that page has javascript
    #it "Page includes javascript" do
    #  expect(page).to have_js
    #end

    # Example: of link clicking and following to next page
    #it "After user clicks archive link, User should see archives" do
    #  click_link('lokakuu 2015')
    #  expect(page).to have_content 'Kuukausi'
    #end

    ### Add more customised business critical frontend tests here #####

  end

  describe "admin-panel" do

    before do
      visit WP.siteurl('/wp-login.php')
    end

    it "There's a login form" do
      expect(page).to have_id "wp-submit"
    end

    # Only run these if we could create a test user
    if WP.user?
      it "Logged in to WordPress Dashboard" do
        within("#loginform") do
          fill_in 'log', :with => WP.user.username
          fill_in 'pwd', :with => WP.user.password
        end
        click_button 'wp-submit'
        # Should obtain cookies and be able to visit /wp-admin
        expect(page).to have_id "wpadminbar"
      end
    end

  end

end

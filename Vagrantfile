# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'
require 'mkmf'
require 'fileutils'
require 'socket'

# Prevent logs from mkmf
module MakeMakefile::Logging
  @logfile = File::NULL
end

DIR = File.dirname(__FILE__)

# Create .vagrant folder
unless File.exist?(File.join(DIR,'.vagrant'))
  FileUtils.mkdir_p( File.join(DIR,'.vagrant') )
end

# Create config file
config_file = File.join(DIR, 'config.yml')
sample_file = File.join(DIR, 'config-sample.yml')

unless File.exists?(config_file)
  # Use sample instead
  FileUtils.copy sample_file, config_file
  puts '==> default: config.yml was not found. Copying defaults from sample configs..'
end

site_config = YAML.load_file(config_file)

# Create private ip address in file
private_ip_file = File.join(DIR,'.vagrant','private.ip')

unless File.exists?(private_ip_file)
  private_ip = "192.168.#{rand(255)}.#{rand(2..255)}"
  File.write(private_ip_file, private_ip)
else
  private_ip = File.open(private_ip_file, 'rb') { |file| file.read }
end

# Multiple public_network mappings need at least 1.7.4
Vagrant.require_version '>= 1.7.4'

# Try to use the vagrant-triggers plugin when using a vagrant version below 2.1.0 or
# if the user has self chosen to do so by setting the VAGRANT_USE_VAGRANT_TRIGGERS
# environment variable.
use_triggers_plugin = false
triggers_plugin_installed = Vagrant.has_plugin? 'vagrant-triggers'
if Gem::Version.new(Vagrant::VERSION) >= Gem::Version.new('2.1.0')
  # Vagrant core triggers are available, but the user can also opt-in to
  # use the plugin if it is installed
  if triggers_plugin_installed
    use_triggers_plugin = true
    if ENV['VAGRANT_USE_VAGRANT_TRIGGERS'].nil?
      # Exit here because starting Vagrant will not work if VAGRANT_USE_VAGRANT_TRIGGERS is
      # not defined but at the same time the vagrant-triggers plugin is installed.
      STDERR.puts 'ERROR: can not use the Vagrant trigger functionality'
      STDERR.puts 'Please uninstall the vagrant-triggers plugin to fix the problem:'
      STDERR.puts '$ vagrant plugin uninstall vagrant-triggers'
      exit 1
    end
  end
else
  # No choice but to use the plugin, but we need to ensure that it is installed
  use_triggers_plugin = true
  if !triggers_plugin_installed
    STDERR.puts 'ERROR: can not use the Vagrant trigger functionality'
    STDERR.puts 'Please update Vagrant to version 2.1.0 or newer to fix the problem'
    exit 1
  end
end

Vagrant.configure('2') do |config|

  # Use host-machine ssh-key so we can log into production
  config.ssh.forward_agent = true

  # Minimum box version requirement for this Vagrantfile revision
  config.vm.box_version = ">= 20200130.0.0"

  # Use precompiled box
  config.vm.box = 'seravo/wordpress'

  # Use the name of the box as the hostname
  config.vm.hostname = site_config['name']

  # Only use avahi if config has this
  # development:
  #   avahi: true
  if site_config['development']['avahi'] && has_internet? and is_osx?
    # The box uses avahi-daemon to make itself available to local network
    config.vm.network "public_network", bridge: [
      "en0: Wi-Fi (AirPort)",
      "en1: Wi-Fi (AirPort)",
      "wlan0"
    ]
  end

  # Use random ip address for box
  # This is needed for updating the /etc/hosts file
  config.vm.network :private_network, ip: private_ip

  config.vm.define "#{site_config['name']}-box"

  if Vagrant.has_plugin? 'vagrant-hostsupdater'
    # Remove hosts when suspending too
    config.hostsupdater.remove_on_suspend = true

    domains = get_domains(site_config)
    config.hostsupdater.aliases = domains - [config.vm.hostname]
  else
    puts 'vagrant-hostsupdater missing, please install the plugin:'
    puts 'vagrant plugin install vagrant-hostsupdater'
    exit 1
  end

  # Disable default vagrant share
  config.vm.synced_folder ".", "/vagrant", disabled: true

  # Sync the folders
  # We have tried using NFS but it's super slow compared to synced_folder
  config.vm.synced_folder DIR, '/data/wordpress/', owner: 'vagrant', group: 'vagrant', mount_options: ['dmode=775', 'fmode=775']


  # For Self-signed ssl-certificate
  ssl_cert_path = File.join(DIR,'.vagrant','ssl')
  unless File.exists? File.join(ssl_cert_path,'development.crt')
    config.vm.provision :shell, :inline => "wp-generate-ssl"
  end

  # Add SSH Public Key from developer home folder into vagrant
  if File.exists? File.join(Dir.home, ".ssh", "id_rsa.pub")
    id_rsa_ssh_key_pub = File.read(File.join(Dir.home, ".ssh", "id_rsa.pub"))
    config.vm.provision :shell, :inline => "echo '#{id_rsa_ssh_key_pub }' >> /home/vagrant/.ssh/authorized_keys && chmod 600 /home/vagrant/.ssh/authorized_keys"
  end

  if use_triggers_plugin
    vagrant_triggers_plugin_triggers(config, site_config)
  else
    vagrant_triggers(config, site_config)
  end

  config.vm.provider 'virtualbox' do |vb|
    # Give VM access to all cpu cores on the host
    cpus = case RbConfig::CONFIG['host_os']
      when /darwin/ then `sysctl -n hw.physicalcpu`.to_i
      when /linux/ then `nproc`.to_i
      else 2
    end

    # Customize memory in MB
    vb.customize ['modifyvm', :id, '--memory', 1536]
    vb.customize ['modifyvm', :id, '--cpus', cpus]

    # Fix for slow external network connections
    vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
    vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
  end

end

##
# Run Vagrant triggers using the vagrant core trigger feature
##
def vagrant_triggers(vagrant_config, site_config)
  vagrant_config.trigger.after :up do |trigger|
    trigger.ruby do |env, machine|
      #Run all system commands inside project root
      Dir.chdir(DIR)

      # Install packages with Composer
      # run it locally if possible
      if find_executable 'composer' and system "composer validate &>/dev/null"
        system "composer install"
      else # run in vagrant
        run_command("composer install --working-dir=/data/wordpress", machine)
      end

      # Sync plugin files from production is so configured to do
      if site_config['production'] != nil && site_config['production']['ssh_port'] != nil and site_config['development']['pull_production_plugins'] == 'always'
        run_command("wp-pull-production-plugins", machine)
      end

      # Sync theme files from production is so configured to do
      if site_config['production'] != nil && site_config['production']['ssh_port'] != nil and site_config['development']['pull_production_themes'] == 'always'
        run_command("wp-pull-production-themes", machine)
      end

      # Database imports
      if site_config['production'] != nil && site_config['production']['ssh_port'] != nil && site_config['development']['pull_production_db'] != 'never' && (site_config['development']['pull_production_db'] == 'always' or confirm("Pull database from production?", false))
        # Seravo customers are asked if they want to pull the production database here

        # Install WordPress with defaults first so the database is not empty. Will automatically skip if WP was already installed.
        run_command("wp core install --url=https://#{site_config['name']}.local --title=#{site_config['name'].capitalize}\
          --admin_email=vagrant@#{site_config['name']}.local --admin_user=vagrant --admin_password=vagrant", machine)
        # Pull production DB
        run_command("wp-pull-production-db", machine)
      elsif File.exists?(File.join(DIR,'.vagrant','shutdown-dump.sql'))
        # Return the state where we last left if WordPress isn't currently installed
        # First part in the command prevents overriding existing database
        run_command("wp core is-installed --quiet &>/dev/null || wp-vagrant-import-db", machine)
      elsif File.exists?(File.join(DIR,'vagrant-base.sql'))
        run_command("wp db import /data/wordpress/vagrant-base.sql", machine)
      else
        # If nothing else was specified just install basic WordPress
        run_command("wp core install --url=https://#{site_config['name']}.local --title=#{site_config['name'].capitalize}\
          --admin_email=vagrant@#{site_config['name']}.local --admin_user=vagrant --admin_password=vagrant", machine)
        notice "Installed default WordPress with user:vagrant password:vagrant"
      end

      # Don't activate git hooks, just notify them
      if File.exists?( File.join(DIR,'.git', 'hooks', 'pre-commit') )
        puts "If you want to use a git pre-commit hook please run 'wp-activate-git-hooks' inside the Vagrant box."
      end

      case RbConfig::CONFIG['host_os']
      when /darwin/
        # Do OS X specific things

        # Trust the self-signed cert in keychain
        ssl_cert_path = File.join(DIR,'.vagrant','ssl')
        unless File.exists?(File.join(ssl_cert_path,'trust.lock'))
          if File.exists?(File.join(ssl_cert_path,'development.crt')) and confirm "Trust the generated ssl-certificate in OS-X keychain?"
            system "sudo security add-trusted-cert -d -r trustRoot -k '/Library/Keychains/System.keychain' '#{ssl_cert_path}/development.crt'"
            # Write lock file so we can remove it too
            touch_file File.join(ssl_cert_path,'trust.lock')
          end
        end
      when /linux/
        # Do linux specific things
      end

      # Attempt to use the asset proxy for production url defined in config.yml
      run_command("wp-use-asset-proxy", machine)

      # Restart nginx because the file system might not have been ready when the certificate was created
      run_command("wp-restart-nginx", machine)

      # Run 'vagrant up' customizer script if it exists
      if File.exist?(File.join(DIR, 'vagrant-up-customizer.sh'))
        notice 'Found vagrant-up-customizer.sh and running it ...'
        Dir.chdir(DIR)
        system 'sh ./vagrant-up-customizer.sh'
      end

      puts "\n"
      notice "Documentation available at https://seravo.com/docs/"
      notice "Visit your site: https://#{site_config['name']}.local"
    end
  end

  vagrant_config.trigger.before [:halt, :destroy] do |trigger|
    trigger.info = "Dump WordPress database into: .vagrant/shutdown-dump.sql"
    trigger.ruby do |env, machine|
      # dump database when closing vagrant
      if vagrant_running?
        begin
          run_command("wp-vagrant-dump-db", machine)
        rescue => e
          notice "Couldn't dump database. Skipping..."
        end
      end
    end
  end
end

##
# Helper function to run a command in a vagrant machine by providing the command
# and a Vagrant::Machine object as parameters. Heavily influenced by
# https://github.com/emyl/vagrant-triggers/blob/master/lib/vagrant-triggers/dsl.rb
##
def run_command(cmd, machine)
  exit_code = 1
  good_exit_codes = (0..255).to_a
  begin
    exit_code = machine.communicate.sudo(cmd, :elevated => true, :good_exit => good_exit_codes) do |channel, data|
      machine.ui.send(:info, data)
    end
  rescue => e
    machine.ui.send(:error, e.message)
  end
  if !good_exit_codes.include? exit_code
    exit exit_code
  end
  return exit_code
end

##
# Run Vagrant triggers in a vagrant-triggers plugin compatible format
##
def vagrant_triggers_plugin_triggers(vagrant_config, site_config)
  # Some useful triggers for better workflow
  vagrant_config.trigger.after :up do
    #Run all system commands inside project root
    Dir.chdir(DIR)

    # Install packages with Composer
    # run it locally if possible
    if find_executable 'composer' and system "composer validate &>/dev/null"
      system "composer install"
    else # run in vagrant
      run_remote "composer install --working-dir=/data/wordpress"
    end

    # Sync plugin files from production is so configured to do
    if site_config['production'] != nil && site_config['production']['ssh_port'] != nil and site_config['development']['pull_production_plugins'] == 'always'
      run_remote "wp-pull-production-plugins"
    end

    # Sync theme files from production is so configured to do
    if site_config['production'] != nil && site_config['production']['ssh_port'] != nil and site_config['development']['pull_production_themes'] == 'always'
      run_remote "wp-pull-production-themes"
    end

    # Database imports
    if site_config['production'] != nil && site_config['production']['ssh_port'] != nil && site_config['development']['pull_production_db'] != 'never' && (site_config['development']['pull_production_db'] == 'always' or confirm("Pull database from production?", false))
      # Seravo customers are asked if they want to pull the production database here

      # Install WordPress with defaults first so the database is not empty. Will automatically skip if WP was already installed.
      run_remote("wp core install --url=https://#{site_config['name']}.local --title=#{site_config['name'].capitalize}\
        --admin_email=vagrant@#{site_config['name']}.local --admin_user=vagrant --admin_password=vagrant")
      # Pull production DB
      run_remote "wp-pull-production-db"
    elsif File.exists?(File.join(DIR,'.vagrant','shutdown-dump.sql'))
      # Return the state where we last left if WordPress isn't currently installed
      # First part in the command prevents overriding existing database
      run_remote "wp core is-installed --quiet &>/dev/null || wp-vagrant-import-db"
    elsif File.exists?(File.join(DIR,'vagrant-base.sql'))
      run_remote "wp db import /data/wordpress/vagrant-base.sql"
    else
      # If nothing else was specified just install basic WordPress
      run_remote("wp core install --url=https://#{site_config['name']}.local --title=#{site_config['name'].capitalize}\
        --admin_email=vagrant@#{site_config['name']}.local --admin_user=vagrant --admin_password=vagrant")
      notice "Installed default WordPress with user:vagrant password:vagrant"
    end

    # Init git if it doesn't exist
    if not File.exists?( File.join(DIR,".git") ) and confirm "There's no git repository. Should we create one?"
      system "git init ."
    end

    # Don't activate git hooks, just notify them
    if File.exists?( File.join(DIR,'.git', 'hooks', 'pre-commit') )
      puts "If you want to use a git pre-commit hook please run 'wp-activate-git-hooks' inside the Vagrant box."
    end

    case RbConfig::CONFIG['host_os']
    when /darwin/
      # Do OS X specific things

      # Trust the self-signed cert in keychain
      ssl_cert_path = File.join(DIR,'.vagrant','ssl')
      unless File.exists?(File.join(ssl_cert_path,'trust.lock'))
        if File.exists?(File.join(ssl_cert_path,'development.crt')) and confirm "Trust the generated ssl-certificate in OS-X keychain?"
          system "sudo security add-trusted-cert -d -r trustRoot -k '/Library/Keychains/System.keychain' '#{ssl_cert_path}/development.crt'"
          # Write lock file so we can remove it too
          touch_file File.join(ssl_cert_path,'trust.lock')
        end
      end
    when /linux/
      # Do linux specific things
    end

    # Attempt to use the asset proxy for production url defined in config.yml
    run_remote "wp-use-asset-proxy"

    # Restart nginx because the file system might not have been ready when the certificate was created
    run_remote "wp-restart-nginx"

    # Run 'vagrant up' customizer script if it exists
    if File.exist?(File.join(DIR, 'vagrant-up-customizer.sh'))
      notice 'Found vagrant-up-customizer.sh and running it ...'
      Dir.chdir(DIR)
      system 'sh ./vagrant-up-customizer.sh'
    end

    puts "\n"
    notice "Documentation available at https://seravo.com/docs/"
    notice "Visit your site: https://#{site_config['name']}.local"
  end

  vagrant_config.trigger.before :halt do
    # dump database when closing vagrant
    dump_wordpress_database
  end

  vagrant_config.trigger.before :destroy do
    # dump database when closing vagrant
    dump_wordpress_database
  end
end

##
# Custom helpers
##
def notice(text)
  puts "==> trigger: #{text}"
end

##
# Dump database into file in vagrant
##
def dump_wordpress_database
  if vagrant_running?
    begin
      notice "dumping the database into: .vagrant/shutdown-dump.sql"
      run_remote "wp-vagrant-dump-db"
    rescue => e
      notice "Couldn't dump database. Skipping..."
    end
  end
end

##
# Create empty file
##
def touch_file(path)
  File.open(path, "w") {}
end

##
# Generate /etc/hosts domain additions
##
def get_domains(config)

  unless config['development'].nil?
    domains = config['development']['domains'] || []
    domains << config['development']['domain'] unless config['development']['domain'].nil?
  else
    domains = []
  end

  # The main domain
  domains << config['name']+".local"

  # Add domain names for included applications for easier usage
  subdomains = %w( www webgrind adminer mailcatcher browsersync info )

  subdomains.each do |domain|
    domains << "#{domain}.#{config['name']}.local"
  end

  domains.uniq #remove duplicates
end

##
# Get boolean answer for question string
##
def confirm(question,default=true)
  if default
    default = "yes"
  else
    default = "no"
  end

  confirm = nil
  until ["Y","N","YES","NO",""].include?(confirm)
    print "#{question} (#{default}): "
    confirm = STDIN.gets.chomp

    if (confirm.nil? or confirm.empty?)
      confirm = default
    end

    confirm.strip!
    confirm.upcase!
  end
  if confirm.empty? or confirm == "Y" or confirm == "YES"
    return true
  end
  return false
end

##
# This is quite hacky but for my understanding the only way to check if the current box state
##
def vagrant_running?
  system("vagrant status --machine-readable | grep state,running --quiet")
end

##
# On OS X we can use a few more features like zeroconf (discovery of .local addresses in local network)
##
def is_osx?
  RbConfig::CONFIG['host_os'].include? 'darwin'
end

##
# Modified from: https://coderrr.wordpress.com/2008/05/28/get-your-local-ip-address/
# Returns true/false
##
def has_internet?
  orig, Socket.do_not_reverse_lookup = Socket.do_not_reverse_lookup, true  # turn off reverse DNS resolution temporarily
  begin
    UDPSocket.open { |s| s.connect '8.8.8.8', 1 }
    return true
  rescue Errno::ENETUNREACH
    return false # Network is not reachable
  end
ensure
  Socket.do_not_reverse_lookup = orig
end

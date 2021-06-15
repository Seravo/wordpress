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
  private_ip = "192.168.250.#{rand(2..255)}"
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
      "en0: Wi-Fi (Wireless)",
      "en1: Wi-Fi (Wireless)",
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
    vb.memory = 1536
    vb.cpus = 2

    # Fix for slow external network connections
    vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
    vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
    vb.customize ['modifyvm', :id, '--uartmode1', 'file', File::NULL]
  end
end

##
# Run native Vagrant triggers
# https://www.vagrantup.com/docs/triggers/usage
##
def vagrant_triggers(vagrant_config, site_config)
  vagrant_config.trigger.after :up do |trigger|
    trigger.ruby do |env, machine|

      # Run all system commands inside project root
      Dir.chdir(DIR)

      # Always wait a couple of seconds before running triggers so that the
      # machine internals have time to bootstrap
      sleep 3

      # Since neither communicate.sudo nor trigger.run_remote supports interactive
      # mode (on other than some special Windows admin mode), call out to system
      # to call back to Vagrant as a hacky way to get interactive mode, and thus
      # wp-development-up can ask the various "pull x, y and z" questions.
      # https://www.rubydoc.info/github/mitchellh/vagrant/Vagrant/Plugin/V2/Communicator#execute-instance_method
      # https://www.vagrantup.com/docs/provisioning/shell
      system "vagrant ssh -c wp-development-up"

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

      # Run 'vagrant up' customizer script if it exists
      if File.exist?(File.join(DIR, 'vagrant-up-customizer.sh'))
        notice 'Found vagrant-up-customizer.sh and running it ...'
        Dir.chdir(DIR)
        system 'sh ./vagrant-up-customizer.sh'
      end

    end
  end

  vagrant_config.trigger.before [:halt, :destroy] do |trigger|
    trigger.ruby do |env, machine|
      # dump database when closing vagrant
      if vagrant_running?
        begin
          # Note! This will run as root
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
# Legacy: Run vagrant-triggers plugin compatible format on older Vagrants
##
def vagrant_triggers_plugin_triggers(vagrant_config, site_config)
  vagrant_config.trigger.after :up do

    # Run all system commands inside project root
    Dir.chdir(DIR)

    # Always wait a couple of seconds before running triggers so that the
    # machine internals have time to bootstrap
    sleep 3

    # Always run this when Vagrant triggers that site is up
    system "vagrant ssh -c wp-development-up"

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

    # Run 'vagrant up' customizer script if it exists
    if File.exist?(File.join(DIR, 'vagrant-up-customizer.sh'))
      notice 'Found vagrant-up-customizer.sh and running it ...'
      Dir.chdir(DIR)
      system 'sh ./vagrant-up-customizer.sh'
    end

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

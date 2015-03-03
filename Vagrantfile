# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'

DIR = File.dirname(__FILE__)

config_file = File.join(DIR, 'config.yml')

if File.exists?(config_file)
  site_config = YAML.load_file(config_file)
else
  raise 'config.yml was not found. Please provide the needed information for your box.'
end

Vagrant.require_version '>= 1.5.1'

Vagrant.configure('2') do |config|

  # Use host-machine ssh-key so we can log into production
  config.ssh.forward_agent = true

  #Use precompiled box
  config.vm.box = 'seravo/wordpress'

  # Required for NFS to work, pick any local IP
  config.vm.network :private_network, ip: '192.168.50.10'

  config.vm.hostname = site_config['name']+"-wp-dev"

  #TODO: add profiler to some easy hostname

  if Vagrant.has_plugin? 'vagrant-hostsupdater'

    host_aliases = [
      site_config['development']['domain'],
      "webgrind."site_config['development']['domain'], #For xdebug
      site_config['name']+".seravo.dev" #test https-domain-alias locally
    ]

    config.hostsupdater.aliases = host_aliases - [config.vm.hostname]
  else
    puts 'vagrant-hostsupdater missing, please install the plugin:'
    puts 'vagrant plugin install vagrant-hostsupdater'
  end

  #We only need to sync this project folder with /data/wordpress/
  config.vm.synced_folder DIR, '/data/wordpress/', owner: 'vagrant', group: 'vagrant', mount_options: ['dmode=776', 'fmode=775']

  #Mount users ssh configs and keys with readonly mode
  config.vm.synced_folder "/home", "/svr/conf.d"

  #Disable default vagrant share
  config.vm.synced_folder ".", "/vagrant", disabled: true

  # Some useful triggers with better workflow
  if Vagrant.has_plugin? 'vagrant-triggers'
    # TODO: Create/Sync database with vagrant up
    config.trigger.after :up do
      confirm = nil
      until ["Y", "y", "N", "n"].include?(confirm)
        confirm = ask "Would you really like pull database from production? (Y/N) "
      end
      if confirm.upcase == "Y"
        run "wp-pull-production-db"
      end

      #Generate ssl

      #TODO self-signed cert in .vagrant folder
    end
    # config.yml should have information about production environment

    # TODO: Activate .git commit hooks with vagrant up
    # - git commit to master should activate all tests
  else
    puts 'vagrant-triggers missing, please install the plugin:'
    puts 'vagrant plugin install vagrant-triggers'
  end

  # TODO-EXTRA:
  # It would be cool to automatically create self-signed ssl cert and put it in autotrust
  # http://kb.kerio.com/product/kerio-connect/server-configuration/ssl-certificates/adding-trusted-root-certificates-to-the-server-1605.html
  # Then drop it when box is halted/destroyed

  config.vm.provider 'virtualbox' do |vb|
    # Give VM access to all cpu cores on the host
    cpus = case RbConfig::CONFIG['host_os']
      when /darwin/ then `sysctl -n hw.ncpu`.to_i
      when /linux/ then `nproc`.to_i
      else 2
    end

    # Customize memory in MB
    vb.customize ['modifyvm', :id, '--memory', 1024]
    vb.customize ['modifyvm', :id, '--cpus', cpus]

    # Fix for slow external network connections
    vb.customize ['modifyvm', :id, '--natdnshostresolver1', 'on']
    vb.customize ['modifyvm', :id, '--natdnsproxy1', 'on']
  end

end
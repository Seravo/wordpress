# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'

DIR = File.dirname(__FILE__)

config_file = File.join(DIR, 'config.yml')

if File.exists?(config_file)
  site_config = YAML.load_file(config_file)
else
  puts 'ERROR: config.yml was not found. Please provide the needed information for your box.'
  exit 0
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
    domains = get_domains(site_config)

    config.hostsupdater.aliases = domains - [config.vm.hostname]
  else
    puts 'vagrant-hostsupdater missing, please install the plugin:'
    puts 'vagrant plugin install vagrant-hostsupdater'
    exit 1
  end

  #We only need to sync this project folder with /data/wordpress/
  config.vm.synced_folder DIR, '/data/wordpress/', owner: 'vagrant', group: 'vagrant', mount_options: ['dmode=776', 'fmode=775']

  #Disable default vagrant share
  config.vm.synced_folder ".", "/vagrant", disabled: true

  #For Self-signed ssl-certificate
  ssl_cert_path = File.join(DIR,'.vagrant','ssl')

  # Add SSH Public Key into vagrant
  if File.exists? File.join(Dir.home, ".ssh", "id_rsa.pub")
    id_rsa_ssh_key_pub = File.read(File.join(Dir.home, ".ssh", "id_rsa.pub"))
    config.vm.provision :shell, :inline => "echo '#{id_rsa_ssh_key_pub }' >> /home/vagrant/.ssh/authorized_keys && chmod 600 /home/vagrant/.ssh/authorized_keys"
  end

  # Some useful triggers with better workflow
  if Vagrant.has_plugin? 'vagrant-triggers'
    # TODO: Create/Sync database with vagrant up
    config.trigger.after :up do

      #Generate ssl-certificate
      generate_self_signed_ssl(site_config) unless File.exists?(File.join(ssl_cert_path,'development.crt'))

      #Add https-domain-alias into configs so that can be tested too
      add_env("HTTPS_DOMAIN_ALIAS","#{site_config['name']}.seravo.dev")
      
      #For WP-Palvelu customers
      if site_config != nil && site_config['production'] != nil && site_config['production']['ssh_port'] != nil
        #Pull production
        run "wp-pull-production-db" if confirm "Pull database from production? (Y/N)"
      elsif File.exists?(File.join(DIR,'.vagrant','vagrant-dump.sql'))
        #Return the state where we last left
        run "wp db import /data/wordpress/.vagrant/vagrant-dump.sql"
      end

      case RbConfig::CONFIG['host_os']
      when /darwin/
        # Do OS X specific things
        if confirm "Do you want to trust the generated ssl-certificate? (Y/N)"
          system "sudo security add-trusted-cert -d -r trustRoot -k '/Library/Keychains/System.keychain' #{ssl_cert_path}/development.crt"
          #Write lock file so we can remove it too
          touch_file "#{ssl_cert_path}/trust.lock"
        end
      when /linux/
        # Do linux specific things
      end

    end

    config.trigger.before :halt do
      #dump database when closing vagrant
      dump_wordpress_database
    end

    config.trigger.before :destroy do
      #dump database when destroying vagrant
      dump_wordpress_database

      #Remove autotrusted certificate from keychain
      case RbConfig::CONFIG['host_os']
      when /darwin/
        # Do OS X specific things
        if File.exists?("#{ssl_cert_path}/trust.lock")
          notice "removing the autotrust from certificate..."
          system "sudo security remove-trusted-cert -d #{ssl_cert_path}/development.crt"
          system "sudo security delete-certificate *.#{config['name']}.dev"
          File.delete("#{ssl_cert_path}/trust.lock")
        end
      when /linux/
        # Do linux specific things
      end
    end

    # TODO: Activate .git commit hooks with vagrant up
    # - git commit to master should activate all tests
  else
    puts 'vagrant-triggers missing, please install the plugin:'
    puts 'vagrant plugin install vagrant-triggers'
    exit 1
  end

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

##
# Custom helpers
##
def notice(text)
  puts "==> trigger: #{text}"
end

def add_ssh_public_key
  public_key_file = "#{Dir.home}/.ssh/id_rsa.pub"
  if File.exist?(public_key_file)
    contents = File.read("#{Dir.home}/.ssh/id_rsa.pub").strip
    run "echo \'#{contents}\' | sudo tee --append /home/vagrant/.ssh/authorized_keys"
  end
end

def add_env(env,value)
  run "echo 'export #{env}=#{value}' | sudo tee --append /etc/container_environment.sh"
  run "echo 'fastcgi_param #{env} #{value};' | sudo tee --append /etc/nginx/fastcgi_params"
end

def dump_wordpress_database
  notice "dumping the database into: .vagrant/vagrant-dump.sql"
  run "wp core is-installed && wp db export /data/wordpress/.vagrant/vagrant-dump.sql"
end

def generate_self_signed_ssl(config)
  vagrant_ssl_path = "/data/wordpress/.vagrant/ssl"
  local_ssl_path = File.join(File.dirname(__FILE__),".vagrant","ssl")

  #Stop now if this is generated already
  return if File.exists?(File.join(local_ssl_path,"development.crt"))
  notice "generating new self signed certificate..."

  #Create directory for ssl
  FileUtils.mkdir_p local_ssl_path

  #bit hacky but nice way to embed file here
  open_ssl_file_content = <<EOF
[req]
distinguished_name = req_distinguished_name
x509_extensions = v3_req
prompt = no

[req_distinguished_name]
C = FI
ST = Pirkanmaa
L = Tampere
O = Seravo Oy
CN = #{config['name']}.dev
emailAddress = wordress@seravo.fi 
[v3_req]
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
basicConstraints = CA:TRUE
subjectAltName = @alt_names

[alt_names]
EOF

  #Append domains into string
  domains = get_domains(config)
  domains.each_with_index {|domain, index|
    open_ssl_file_content += "DNS.#{index+1} = #{domain}\n"
  }
  #Write to file
  File.open(File.join(local_ssl_path,"openssl.cnf"), 'w') { |file| file.write(open_ssl_file_content) }
  sleep(1.0/2)

  run "openssl req -x509 -nodes -days 3650 -newkey rsa:2048 -keyout #{local_ssl_path}/development.key -out #{local_ssl_path}/development.crt -config #{local_ssl_path}/openssl.cnf"
  run "sudo service nginx restart && rm /data/log/nginx-error.log"
end

def touch_file(path) 
  File.open(path, "w") {}
end

def get_domains(config)
  domains = config['development']['domains'] || []
  domains << config['development']['domain'] unless config['development']['domain'].nil?

  domains << "www."+config['name']+".dev"
  domains << config['name']+".seravo.dev" #test https-domain-alias locally
  domains << "webgrind."+config['name']+".dev" #For xdebug
  domains << "adminer."+config['name']+".dev" #For adminer
  domains.uniq #remove duplicates
end

def confirm(question)
  confirm = nil
  until ["Y", "y", "N", "n"].include?(confirm)
    confirm = ask "#{question} "
  end
  if confirm.upcase == "Y"
    return true
  end
  return false
end
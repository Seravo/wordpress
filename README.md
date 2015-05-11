# Seravo WordPress

Brought to you by Seravo and [wp-palvelu.fi](http://wp-palvelu.fi).

A WordPress project layout for use with Vagrant, Git, Composer and Nginx. If you develop using this Vagrant environment as a base, your development environment will be as compatible with the Seravo WP-Palvelu environment as possible.

### Features
* Includes Nginx, Redis, Git, PHP5-FPM, Xdebug, PHP Codesniffer...
* Git hooks to test your code when running commits
* Test https:// locally with self-signed certs (and trust them automatically in OS X)
* Advanced wordpress integration tests with rspec
* [Mailcatcher](http://mailcatcher.me/)
* [Webgrind](https://code.google.com/p/webgrind/)
* [Adminer](http://www.adminer.org/)

## Installation

### Linux (debian)
To use virtualbox make sure you have ```vt-x``` enabled in your bios.

```
$ apt-get install vagrant virtualbox virtualbox-dkms nfsd git
$ git clone https://github.com/Seravo/wordpress ~/wordpress-dev
$ cd ~/wordpress-dev
$ vagrant plugin install vagrant-hostsupdater vagrant-triggers
$ vagrant up
# Answer (y/n) for interactive installation script
```

### OS X

1. [Install Xcode](https://developer.apple.com/xcode/downloads/)
2. [Install Vagrant](http://docs.vagrantup.com/v2/installation/) (**1.7.2 or later**)
3. [Install Virtualbox](https://www.virtualbox.org/wiki/Downloads)
4. Clone this repo
5. Do the installation in terminal:
```
$ vagrant plugin install vagrant-hostsupdater vagrant-triggers
$ vagrant up
# Answer (y/n) for interactive installation script
```
### Windows

To use virtualbox make sure you have ```vt-x``` enabled in your bios. You might need to disable ```hyper-v``` in order to use virtualbox.

1. [Install Vagrant](http://docs.vagrantup.com/v2/installation/) (**1.7.2 or later**)
2. [Install Virtualbox](https://www.virtualbox.org/wiki/Downloads)
3. Clone this repo
4. Do the installation in terminal:
```
$ vagrant plugin install vagrant-hostsupdater vagrant-triggers
$ vagrant up
# Answer (y/n) for interactive installation script
```

Navigate to http://wordpress.dev or run `vagrant ssh` to get started.

Default credentials are:
  user: vagrant 
  password: vagrant

## Configuration

You can edit the siteurl and domains by creating config.yml file in the root of the repo.
See ```config-sample.yml```.

```yaml
###
# Configuration for Vagrant
###
name: wordpress #This is used for all of the domains
production:
  # This is used to automatically fetch data from a staging/production environment
  # This is for WP-Palvelu customers. Leave blank if you don't want to use this feature.
  domain: wordpress.seravo.fi
  ssh_port: 10000
development:
  # Domains are automatically mapped to Vagrant with /etc/hosts modifications
  domains:
    - wordpress.seravo.fi
    - wordpress.dev

```

### Folder Structure

The root of this repository equals the contents of the directory ```/data/wordpress``` in the WP-palvelu.fi service.

```
├── composer.json # Use composer for package handling
├── composer.lock # Tells which composer package versions are currently used
├── Vagrantfile # Advanced vagrant environment and scripts packaged in Vagrantfile
├── config.yml # See about Configuration above
├── tests # Here you can include tests for your wordpress instance
│   └── rspec # Our default tests use rspec/poltergeist/phantomjs since we have found them very effective.
│       ├── test.rb
│       └──
│   
├── nginx # Here you can have your custom modifications to nginx which are also used in production
│   ├── custom.conf # Default file with few examples to get started
│   └──
├── scripts
│   ├── hooks # Git hooks for your project
│   │   ├── pre-commit # This is run after every commit
│   │   └──
│   │
│   ├── Wordpress
│   │   └── Installer.php #Additional composer scripts
│   │
│   └── run-tests # Bash-script as an interface for your tests in WP-Palvelu Production and Dev environment
├── vendor # Composer packages go here
└── htdocs # This is the web root of your site
    ├── wp-content # wp-content is moved out of core
    │   ├── mu-plugins
    │   ├── plugins
    │   ├── themes
    │   └── languages
    ├── wp-config.php
    ├── index.php
    └── wordpress # Wordpress Core installed by composer
        ├── wp-admin
        ├── index.php
        ├── wp-load.php
        └── ...
```

## WordPress plugins

The composer.json contains some plugins and themes that are likely to be useful for pretty much every installation. For particular use cases see our list of recommended plugins at http://wp-palvelu.fi/lisaosat/

## TODO

* Add HHVM to Vagrant and the possibility to easily change between PHP5-FPM and HHVM

## Credits

Layout Heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)
Development stack inspired by [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV)

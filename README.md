# Seravo WordPress

Brought to you by [wp-palvelu.fi](http://wp-palvelu.fi).

A WordPress project layout for use with Vagrant, Git, Composer and Nginx.

## Included
* [Mailcatcher](http://mailcatcher.me/)
* [Webgrind](https://code.google.com/p/webgrind/)
* [Adminer](http://www.adminer.org/)
* Git hooks to test your code when running commits
* Test https:// locally with self-signed certs (and trust them automatically in OS X)
* Advanced wordpress integration tests with rspec
* Nginx, xDebug, PHP5-FPM, Redis, Git, PHP Codesniffer...

## Installation for Vagrant

1. Clone this repo.
2. Install

```
$ vagrant plugin install vagrant-hostsupdater
$ vagrant plugin install vagrant-triggers
$ vagrant up
# Answer (y/n) for interactive installation script
```

3. Navigate to http://wordpress.dev or run `vagrant ssh` to get started.

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

```
├── composer.json # Use composer for package handling
├── composer.lock
├── Vagrantfile # Advanced vagrant environment and scripts packaged in Vagrantfile
├── config.yml # See about Configuration above
├── tests
│   ├── rspec
│   │   ├── test.rb
│   │   └── 
│   ├──
│   └──
├── nginx
│   ├── rspec
│   │   ├── test.rb
│   │   └── 
│   ├──
│   └──
├── scripts
│   ├── hooks
│   │   ├── pre-commit #Git hooks for your project
│   │   └── 
│   ├── Wordpress
│   │   └── Installer.php #Additional composer scripts
│   │
│   └── run-tests #MAIN Bash script which determines the testing of your wordpress 
├── vendor
└── htdocs
    ├── wp-content
    │   ├── mu-plugins
    │   ├── plugins
    │   └── themes
    ├── wp-config.php
    ├── index.php
    └── wordpress
```

## Todo:
Add HHVM into vagrant and possibility to easily change between php5-fpm and hhvm

## Credits:

Layout Heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)
Development stack inspired by [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV)

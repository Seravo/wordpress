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

1. Clone this repo.
2. Install Vagrant
3. Install

```
$ vagrant plugin install vagrant-hostsupdater vagrant-triggers
$ vagrant up
# Answer (y/n) for interactive installation script
```

4. Navigate to http://wordpress.dev or run `vagrant ssh` to get started.

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
│   └── run-tests #MAIN Bash script which determines the testing of your WordPress
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

## WordPress plugins

The composer.json contains some plugins and themes that are likely to be useful for pretty much every installation. For particular use cases see our list of recommended plugins at http://wp-palvelu.fi/lisaosat/

## TODO

* Add HHVM to Vagrant and the possibility to easily change between PHP5-FPM and HHVM

## Credits

Layout Heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)
Development stack inspired by [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV)

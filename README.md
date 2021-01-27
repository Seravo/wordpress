![Seravo.com](https://seravo.com/wp-content/themes/seravo/images/seravo-banner-808x300.png)

# Seravo WordPress project template
[![Build Status](https://travis-ci.org/Seravo/wordpress.svg?branch=master)](https://travis-ci.org/Seravo/wordpress)

Brought to you by [Seravo.com](https://seravo.com).

A WordPress project layout for use with Git, Composer and Nginx. It also includes a config a Vagrant box and Docker image for local development.

This same project layout is used by default on all [Seravo.com](https://seravo.com) instances for easy deployment workflow. Contents of this repository equals to what you would have on the server in the directory `/data/wordpress/`.

## Documentation

Please see our documentation at https://seravo.com/docs/ on general information about git workflow with this project template.

## Installation

> Please see our documentation at https://seravo.com/docs/development/how-to-install/ on how to install Vagrant and its dependencies.

## Features

* Includes Nginx, MariaDB, PHP7, PHP8, Redis and Git for running WordPress in modern stack.
* Git hooks to test your code to make sure that only high quality code is committed into git
* Advanced WordPress acceptance tests with Codeception and headless Chrome
* [PHP Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) code style and quality analyzer
* Includes self-signed certs (and trust them automatically in OS X) to test https:// locally
* [Xdebug](http://xdebug.org/) and [Webgrind](https://code.google.com/p/webgrind/) for debugging and profiling your application
* [Mailcatcher](http://mailcatcher.me/) to imitate as SMTP server to debug mails
* [Adminer](http://www.adminer.org/) for a graphical interface to manage your database
* [BrowserSync](http://browsersync.io) as automatic testing middleware for WordPress

### Credentials for vagrant

WordPress:
```
user:     vagrant
password: vagrant
```

MariaDB (MySQL):
```
user:     root
password: root
```

## Development

The layout of this repo is designed in a way which allows storing the site in version control without exposing any confidential data. By default all sensitive data is ignored by git.

All plugins are handled by Composer so they are ignored by git. If you create custom plugins, force add them to git so that they are tracked or add new lines into `.gitignore` to not ignore.

Example of not ignore line in `.gitignore`: `!htdocs/wp-content/plugins/your-plugin/`

If you create custom themes, they are automatically tracked in git.

Best way to develop custom plugins and themes is to add them into their own repositories and install them by composer.
You can do this by adding `composer.json` for your plugin/theme and then requiring them in your project like:

```json
"repositories": [
  {
      "type": "vcs",
      "url": "https://github.com/your-name/custom-plugin.git"
  }
],
"require": {
    "your-name/custom-plugin": "*"
}
```

## Updates

Vagrant will let you know as soon as a new version of the Vagrant box is available.

To download and update your Vagrant box to use the newest image run:
```
$ vagrant box update
$ vagrant destroy
$ vagrant up
```

## Configuration

### config.yml

Change `name` in config.yml to change your site name. This is used in quite some places in development environment.

Add `production => domain` and `production => ssh_port` to sync with your production instance.

Add new domains under `development => domains` before first vagrant up to have extra domains.

See `config-sample.yml` for more.

## The Layout

The root of this repository equals the contents of the directory `/data/wordpress` in the Seravo.com instance.

```
├── config.yml # Project name, domains and other configuration
├── composer.json # Composer definition, used to pull in WordPress and plugins
├── composer.lock # Composer lock file. This is safe to delete and ignore as detailed dependency control is not relevant in WordPress.
├── gulpfile.js # Gulp example with correct paths
├── Vagrantfile # Vagrantfile for Seravo/WordPress Vagrant box
│
├── nginx # Custom modifications to Nginx which are also used in production
│   └── examples.conf # Some examples to get started
│   └── anything.conf # Your own config files can be named anything *.conf
│
├── scripts
│   ├── hooks # Git hooks for your project
│   │   ├── pre-commit # Run after every git commit
│   │   └── post-receive # Run after every git pull/push
│   │
│   ├── WordPress
│   │   └── Installer.php # Composer helper for WordPress installation
│   │
│   └── run-tests # Bash script as an interface for your tests in Seravo's production and development environments
│
├── vendor # Composer packages go here
└── htdocs # The web root of your site
    ├── wp-content # Directory moved out of WordPress core for git compatibility
    │   ├── mu-plugins
    │   ├── plugins
    │   ├── themes
    │   └── languages
    ├── wp-config.php
    ├── wp-load.php
    ├── index.php
    └── wordpress # WordPress core
        ├── wp-admin
        ├── index.php
        └── ...
```

## Credits

* Directory layout heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)
* Development stack inspired by [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV)

Copyright Seravo Oy, 2015–2021 and contributors. Available under the GPLv3 license.

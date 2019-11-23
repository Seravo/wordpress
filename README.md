-![Seravo.com](https://seravo.com/wp-content/themes/seravo/images/seravo-banner-808x300.png)

# Seravo WordPress
[![Build Status](https://travis-ci.org/Seravo/wordpress.svg?branch=master)](https://travis-ci.org/Seravo/wordpress)

Brought to you by [Seravo.com](https://seravo.com).

A WordPress project layout for use with Git, Composer and Nginx. It also includes a config for an opinionated Vagrant box.

This same project layout is used by default on all [Seravo.com](https://seravo.com) instances for easy deployment workflow. Contents of this repository equals to what you would have on the server in the directory /data/wordpress/.

## Documentation

Please see our documentation at https://seravo.com/docs/ on general information about git workflow with this project template.

## Installation

> Please see our documentation at https://seravo.com/docs/development/how-to-install/ on how to install Vagrant and its dependencies.

## Features
* Includes Nginx, MariaDB, PHP5, PHP7, HHVM, Redis and Git for running WordPress in modern stack.
* Git hooks to test your code to make sure that only high quality code is committed into git
* Advanced WordPress acceptance tests with Codeception and headless Chrome
* [PHP Codesniffer](https://github.com/squizlabs/PHP_CodeSniffer) code style and quality analyser
* Includes self-signed certs (and trust them automatically in OS X) to test https:// locally
* [Xdebug](http://xdebug.org/) and [Webgrind](https://code.google.com/p/webgrind/) for debugging and profiling your application
* [Mailcatcher](http://mailcatcher.me/) to imitate as SMTP server to debug mails
* [Adminer](http://www.adminer.org/) for a graphical interface to manage your database
* [BrowserSync](http://browsersync.io) as automatic testing middleware for WordPress

Mailcatcher can be used to emulate emails use mailcatcher.wordpress.local (vagrant).

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

The layout of this repo is designed in a way which allows you to open source your site without exposing any confidential data. By default all sensitive data is ignored by git.

All plugins are handled by composer so they are ignored by git. If you create custom plugins, force add them to git so that they are tracked or add new lines into .gitignore to not ignore.

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
Vagrant will let you know as soon as a new version of the Vagrant box is available. You may download the newest box via `$ vagrant box update`

To update your vagrant to use the new image run:
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

See `config-sample.yml` for more

## The Layout

The root of this repository equals the contents of the directory `/data/wordpress` in the Seravo.com instance.

```
├── config.yml # See about Configuration above
├── composer.json # Use composer for package handling
├── composer.lock
├── gulpfile.js # Example for using gulp
├── Vagrantfile # Advanced vagrant environment and scripts packaged in Vagrantfile
│
├── tests # Here you can include tests for your WordPress instance
│
├── nginx # Here you can have your custom modifications to Nginx which are also used in production
│   └── examples.conf # Some examples to get started
│   └── anything.conf # Your own config files can be named anything *.conf.
│
├── scripts
│   ├── hooks # Git hooks for your project
│   │   ├── pre-commit # This is run after every commit
│   │   └──
│   │
│   ├── WordPress
│   │   └── Installer.php # Additional composer scripts
│   │
│   └── run-tests # Bash-script as an interface for your tests in Seravo's production and development environments
│
├── vendor # Composer packages go here
└── htdocs # This is the web root of your site
    ├── wp-content # wp-content is moved out of core
    │   ├── mu-plugins
    │   ├── plugins
    │   ├── themes
    │   └── languages
    ├── wp-config.php
    ├── wp-load.php
    ├── index.php
    └── wordpress # WordPress Core installed by composer
        ├── wp-admin
        ├── index.php
        └── ...
```

## WordPress plugins

The composer.json contains some plugins and themes that are likely to be useful for pretty much every installation. For particular use cases see our list of recommended plugins at http://wp-palvelu.fi/lisaosat/

Note that all plugins are installed, but not active by default. To activate them, run `vagrant ssh -c "wp plugin activate --all"`.

## Credits

Directory layout heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)
Development stack inspired by [VVV](https://github.com/Varying-Vagrant-Vagrants/VVV)

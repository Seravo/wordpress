# Seravo WordPress

Brought to you by [wp-palvelu.fi](http://wp-palvelu.fi).

A WordPress project layout for use with Vagrant, Git, Composer and Nginx.


## Installation for Vagrant

1. Clone this repo.
2. Install
```
composer update
vagrant up
```
3. Navigate to http://wordpress.dev or run `vagrant ssh` to get started.

Note: You can modify your project details such as the URL in config.yml.


## Documentation

TODO: Actual documentation.

### Folder Structure

```
├── composer.json
├── tests
│   ├── rspec
│   │   ├── 
│   │   └── 
│   ├── phpunit
│   │   ├── 
│   │   └── 
│   ├──
│   └──
│
├── nginx.conf   
├── scripts
│   ├── pre-commit.sh
│   └── run-tests.sh
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

## Credits:

Heavily inspired by [roots/bedrock](https://github.com/roots/bedrock)

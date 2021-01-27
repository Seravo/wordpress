<?php
/*
 * Usually wp-content is inside WordPress core, but in this project template it
 * is moved out of core wordpress directory, which is an ignored directory in
 * git and which might also get occasionally wiped out by Composer runs.
 *
 └── htdocs
    ├── wp-content
    │   ├── mu-plugins
    │   ├── plugins
    │   ├── themes
    │   └── languages
    ├── wp-config.php
    ├── index.php
    ├── wp-load.php
    └── wordpress # WordPress core installed by Composer
        ├── wp-admin
        ├── index.php
        ├── wp-load.php
        └── ...
 *
 * Some popular plugins have an antipattern to do this:
 *   require_once('../../../wp-load.php');
 *
 * They require wp-load.php straight from core usually to have some special ajax
 * functionality. This file is where wp-load.php would typically be and fixes
 * these problems by requiring wp-load.php from where it really is.
 *
 */
require_once 'wordpress/wp-load.php';

<?php
/**
 * PHPUnit prepend file — runs BEFORE vendor/autoload.php is included.
 * Defines the TT_APP sentinel so core/helpers.php and core/db.php
 * do not exit when they are loaded during autoloading.
 */
if (!defined('TT_APP')) {
    define('TT_APP', true);
}

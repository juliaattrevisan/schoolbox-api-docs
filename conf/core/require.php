<?php
/**
 * Schoolbox global dependencies
 *
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
// TODO move this block to 'www/conf/conf.php'
if (PHP_SAPI === 'cli') {
    // Was an instance id specified as a CLI option? Check this before calling core require.
    $instanceOpt = \getopt('', ['instance:']);
    if (isset($instanceOpt['instance'])) {
        // bind the instance ID as given
        define('INSTANCE_ID', $instanceOpt['instance']);
    }
}
// Instance config
require_once __DIR__ . '/../../www/conf/conf.php';

// Class auto-loading
\set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . \get_include_path());
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();

// Global functions and constants
require_once __DIR__ . '/../../www/include/stdfuncs.php';

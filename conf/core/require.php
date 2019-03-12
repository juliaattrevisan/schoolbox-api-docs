<?php
/**
 * Schoolbox global dependencies
 *
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Lumberjack\Logger;

if (PHP_SAPI === 'cli') {
    // Was an instance id specified as a CLI option? Check this before calling core require.
    $instanceOpt = \getopt('', ['instance:']);
    if (isset($instanceOpt['instance'])) {
        // bind the instance ID as given
        define('INSTANCE_ID', $instanceOpt['instance']);
    }
}

require_once __DIR__ . '/../../www/conf/conf.php';
require_once SITE_root . '/www/include/stdfuncs.php';
require_once SITE_root . '/library/constants.php';

set_include_path('.' . PATH_SEPARATOR . SITE_root . PATH_SEPARATOR . get_include_path());
require_once __DIR__ . '/../../library/Autoloader.php';
Autoloader::initialise();

if (PHP_SAPI === 'cli') {
    // TODO - for now we need to ensure $fields and $opts are in scope when this returns for backwards compatibility.
    // TODO clean this up so that callers use the required values, not the returned value (It's bad practice to return values from requires.)
    // TODO instead add a function to scope
    $fields = \array_merge($fields ?? [], [Logger::FIELD_PORCELAIN, Logger::FIELD_MEM, Logger::FIELD_TRUE_MEM, Logger::FIELD_PID]);
    $opts   = \getopt(($shortOpts ?? '') . 'v', $fields);
    return $opts;
}


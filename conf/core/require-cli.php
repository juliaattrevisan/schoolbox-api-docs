<?php
/**
 *
 * This is the standard autoload of any CLI script
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Lumberjack\Logger;

// Was an instance id specified as a CLI option? Check this before calling core require.
$instanceOpt = \getopt('', ['instance:']);
if (isset($instanceOpt['instance'])) {
    // bind the instance ID as given
    define('INSTANCE_ID', $instanceOpt['instance']);
}

require __DIR__ . '/require.php';

// TODO - for now we need to ensure $fields and $opts are in scope when this returns for backwards compatibility.
// TODO clean this up so that callers use the required values, not the returned value (It's bad practice to return values from requires.)
$fields = \array_merge($fields ?? [], [Logger::FIELD_PORCELAIN, Logger::FIELD_MEM, Logger::FIELD_TRUE_MEM, Logger::FIELD_PID]);
$opts   = \getopt(($shortOpts ?? '') . 'v', $fields);
return $opts;

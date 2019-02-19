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

$fields = \array_merge($fields ?? [], [Logger::FIELD_PORCELAIN, Logger::FIELD_MEM, Logger::FIELD_TRUE_MEM, Logger::FIELD_PID]);
return \getopt(($shortOpts ?? '') . 'v', $fields);

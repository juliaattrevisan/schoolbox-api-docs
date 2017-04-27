<?php
/**
 *
 * This is the standard autoload of any CLI script
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

// merge values
$f = [ 'porcelain', 'mem', 'truemem', 'pid', 'instance:' ];
$fields = isset($fields) ? array_merge($fields, $f) : $f;
unset ($f);

$opts = getopt('v', $fields);

if (isset($opts['instance'])) {
    // bind the instance ID as given
    define('INSTANCE_ID', $opts['instance']);
}

require __DIR__ . '/require.php';

return $opts;
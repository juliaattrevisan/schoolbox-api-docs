<?php
/**
 *
 * This is the standard autoload of any CLI script
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid', 'instance:');

$opts = getopt('v', $fields);

if (isset($opts['instance'])) {
    // bind the instance ID as given
    define('INSTANCE_ID', $opts['instance']);
}

require __DIR__ . '/require.php';

return $opts;
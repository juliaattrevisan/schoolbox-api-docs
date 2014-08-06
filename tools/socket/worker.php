#!/usr/bin/php
<?php
use Alaress\Lumberjack\Logger;


/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Worker', array(
	'errors' => E_ALL
));

$kernel->run(function($di, $loop, $debug, $options) {

        $level  = isset($_ENV['level'])  ? $_ENV['level']  : 200;
        $fields = isset($_ENV['fields']) ? $_ENV['fields'] : array('mem');

        new \Schoolbox\Realtime\Pipe\WorkerProcess($loop, new Logger(STDOUT, STDERR, $level, $fields), null);
});

// yup. that's all you need to do.
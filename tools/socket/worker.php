#!/usr/bin/php
<?php

use Alaress\Cli\Debug;

// this gets routed to err.log by the parent process
error_reporting(E_ALL);

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Worker');

$kernel->run(function($di, $loop, $debug, $options) {
	$logfile = \Schoolbox\Config\ConfigMapper::getInstance()->get('logs_dir')."/sched.worker.log";
	new \Schoolbox\Realtime\Pipe\WorkerProcess($loop, null, new Debug(fopen($logfile, 'a')));
});

// yup. that's all you need to do.
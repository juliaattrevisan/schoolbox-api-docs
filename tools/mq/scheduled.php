#!/usr/bin/php
<?php

use Alaress\Cli\Debug;
use Aura\Di;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Scheduler;
use Schoolbox\Realtime\Schedule\SchedulerDispatchStrategy;

/**
 * The Schedule Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


$opts = getopt('v');

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Schedule', array(
	'verbose' => isset($opts['v']),
	'errors'  => E_ALL
));

$sched = null;

$kernel->registerCustomShutdown(function() use (&$sched) {
	if(!$sched instanceof Scheduler) {
		return;
	}

	$sched->getDispatcher()->releaseWorkerPool();
});

// CREATE SCHEDULER APP
$kernel->run(function($di, $loop, $debug, $opts) use (&$sched) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var Debug $debug
	 * @var array $opts
	 */

	try {
		$sched = new Scheduler($loop, new SchedulerDispatchStrategy(), $debug);

		// read STDIN in the stream loop so we can communicate with it in dev mode
		$stdin = new \React\Stream\Stream(STDIN, $loop);
		$stdin->on('data', function($data) use ($sched) {
			$sched->_stdincmd($data);
		});
	}
	catch(AMQPRuntimeException $e) {
		$debug->v("Unable to connect to RabbitMQ: server is offline.",'!');
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}

});

#!/usr/bin/php
<?php

use PhpAmqpLib\Exception\AMQPRuntimeException;
use React\EventLoop\Factory;
use Schoolbox\Realtime\Schedule\Scheduler;
use Schoolbox\Realtime\Schedule\SchedulerDispatchStrategy;
use Schoolbox\Realtime\Verboser;

/**
 * The Schedule Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../socket/mini-bootstrap.php');

$opts = getopt('v');

$verbose = isset($opts['v']);

if($verbose) {
	error_reporting(E_ALL);

	echo <<<EOL

 ||
 || Schoolbox Schedule Daemon
 ||


EOL;
}

$verboser = new Verboser($verbose ? STDOUT : null);

$loop = Factory::create();

// CREATE SCHEDULER
try {
	$sched = new Scheduler($loop, new SchedulerDispatchStrategy(), $verboser);

	// read STDIN in the stream loop so we can communicate with it in dev mode
	$stdin = new \React\Stream\Stream(STDIN, $loop);
	$stdin->on('data', function($data) use ($sched) {
		$sched->_stdincmd($data);
	});

}
catch(AMQPRuntimeException $e) {
	$verboser->v("Unable to connect to RabbitMQ: server is offline.",'!');
	$n = new \Schoolbox\Notif\Notif(\Schoolbox\Notif\Notif::TOPIC_NOTIFICATION);
	$n->alert('RabbitMQ is offline');
	exit;
}


$loop->run();

$verboser->v("Schedule daemon terminating.");
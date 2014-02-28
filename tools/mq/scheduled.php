#!/usr/bin/php
<?php
use PhpAmqpLib\Exception\AMQPRuntimeException;
use React\EventLoop\Factory;
use Schoolbox\ConfigLoader;
use Schoolbox\Realtime\Schedule\Scheduler;
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
	$sched = new Scheduler($loop, $verboser, new ConfigLoader(), true);
}
catch(AMQPRuntimeException $e) {
	$verboser->v("Unable to connect to RabbitMQ: server is offline.",'!');
	$n = new \Schoolbox\Notif\Notif(\Schoolbox\Notif\Notif::TOPIC_NOTIFICATION);
	$n->critical('RabbitMQ is offline');
	exit;
}

$loop->run();

$verboser->v("Schedule daemon terminating.");
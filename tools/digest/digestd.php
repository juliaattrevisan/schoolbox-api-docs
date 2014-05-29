#!/usr/bin/php
<?php

use Alaress\Cli\Debug;
use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Digest\DigestScheduler;

/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


$opts = getopt('v');

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest', array(
	'verbose' => isset($opts['v'])
));



// CREATE DIGEST APP
$kernel->run(function($di, $loop, $debug, $opts) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var Debug $debug
	 * @var array $opts
	 */

	try {
		new DigestScheduler($loop, new DefaultDispatchStrategy(), $debug);
	}
	catch(AMQPRuntimeException $e) {
		$debug->v("Unable to connect to RabbitMQ: server is offline.",'!');
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}

});

#!/usr/bin/php
<?php

use Alaress\Async\Stomp\Subscriber;
use Alaress\Cli\Debug;
use Aura\Di;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Rerouter\Rerouter;
use Schoolbox\Realtime\Rerouter\RerouterDispatchStrategy;
use Schoolbox\Realtime\Rerouter\UserSubscriptionList;

/**
 * The Reroute Daemon.
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


$opts = getopt('v');

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Reroute', array(
	'verbose' => isset($opts['v']),
	'errors'  => E_ALL
));

$rrtr = null;

$kernel->registerCustomShutdown(function() use (&$rrtr) {
	if(!$rrtr instanceof Rerouter) {
		return;
	}

	if($d = $rrtr->getDispatcher()) {
		$d->releaseWorkerPool();
	}
});

// CREATE SCHEDULER APP
$kernel->run(function($di, $loop, $debug, $opts) use (&$rrtr) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var Debug $debug
	 * @var array $opts
	 */
	try {

		$stompFactory = new \React\Stomp\Factory($loop);

		$client = $stompFactory->createClient(array(
			'host'      => Cfg::get('amqp_host'),
			'port'      => Cfg::get('amqp_stomp_port'),
			'vhost'     => '/',
			'login'     => Cfg::get('amqp_user'),
			'passcode'  => Cfg::get('amqp_pass'),
		));

		$rrtr = new Rerouter($loop, new RerouterDispatchStrategy(), $debug, new Subscriber($client, new UserSubscriptionList()));
	}
	catch(AMQPRuntimeException $e) {
		$debug->v("Unable to connect to RabbitMQ: server is offline.",'!');
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}
});

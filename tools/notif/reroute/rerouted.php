#!/usr/bin/hhvm
<?php

use Alaress\Async\Stomp\Subscriber;
use Aura\Di;
use Psr\Log\LoggerInterface;
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


$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Reroute', array(
	'verbose' => isset($opts['v']),
	'errors'  => E_ALL,
    'fields'  => array_values($fields),
    'flags'   => $flags
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
$kernel->run(function($di, $loop, $log, $opts) use (&$rrtr) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var LoggerInterface $log
	 * @var array $opts
	 */
	try {

		$stompFactory = new \React\Stomp\Factory($loop);

		$conf = new \Schoolbox\Config\Loader();

		$client = $stompFactory->createClient(array(
			'host'      => $conf->get('amqp_host'),
			'port'      => $conf->get('amqp_stomp_port'),
			'vhost'     => '/',
			'login'     => $conf->get('amqp_user'),
			'passcode'  => $conf->get('amqp_pass'),
		));

		$rrtr = new Rerouter($loop, new RerouterDispatchStrategy($opts['flags']), $log, new Subscriber($client, new UserSubscriptionList()));
	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.", array($e));
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}
});

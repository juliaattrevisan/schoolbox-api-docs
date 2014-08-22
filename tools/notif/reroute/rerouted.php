#!/usr/bin/hhvm
<?php

use Alaress\Async\Stomp\Subscriber;
use Alaress\Redis;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\Builder\Plan\ReroutePlan;
use Schoolbox\Notif\Queue\UserQueueFactory;
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

$rerouter = null;

$kernel->registerCustomShutdown(function() use (&$rerouter) {
	if(!$rerouter instanceof Rerouter) {
		return;
	}

	if($d = $rerouter->getDispatcher()) {
		$d->releaseWorkerPool();
	}
});

// CREATE SCHEDULER APP
$kernel->run(function($di, $loop, $log, $opts) use (&$rerouter) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var LoggerInterface $log
	 * @var array $opts
	 */
	try {
        // setup external connections

        // db
        $conf = new \Schoolbox\Config\Loader();

        // stomp
        $stompf = new \React\Stomp\Factory($loop);
        $stompc = $stompf->createClient(array(
            'host'     => $conf->get('amqp_host'),
            'port'     => $conf->get('amqp_stomp_port'),
            'vhost'    => '/',
            'login'    => $conf->get('amqp_user'),
            'passcode' => $conf->get('amqp_pass'),
        ));

        // redis
        $redisf = new Redis();
        $redisc = $redisf->getClientInstance();

        // setup dependencies
        $notif      = new Notif(Notif::TOPIC_SYSTEM, $conf);
        $uqf        = new UserQueueFactory(new ReroutePlan($redisc));
        $subscriber = new Subscriber($stompc, new UserSubscriptionList($uqf));
        $strat      = new RerouterDispatchStrategy($opts['flags']);

        // throw all the above into the class we wanted in the first place
        $rerouter   = new Rerouter($loop, $strat, $log, $subscriber, $uqf, $notif);
	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.", array($e));
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}
});

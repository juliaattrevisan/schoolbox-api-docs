#!/usr/bin/php
<?php

use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\Stomp\Subscriber;
use Alaress\Async\UserQueue\UserQueue;
use Alaress\Redis;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\Notif\Queue\QueueFactory;
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


$fields = array('porcelain', 'mem', 'truemem', 'pid', 'bind');

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
	'verbose' => isset($opts['v']) ? $opts['v'] : null,
	'errors'  => E_ALL,
    'fields'  => array_values($fields),
    'flags'   => $flags,
    'bind'    => isset($opts['bind'])
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
        $conf = ConfigMapper::getInstance();

        // stomp
        $stompf = new \React\Stomp\Factory($loop);
        $stompc = $stompf->createClient(array(
            'host'     => $conf->get('amqp_host'),
            'port'     => $conf->get('amqp_stomp_port'),
            'vhost'    => $conf->get('amqp_vhost'),
            'login'    => $conf->get('amqp_user'),
            'passcode' => $conf->get('amqp_pass'),
        ));

        $stompc->connect()->then(function ($client) use (&$rerouter, $loop, $log, $opts, $conf) {

            // amqp
            $amqp = Connector::getInstance();

            // make sure our endpoint exists
            $mqc = \Schoolbox\Notif\MQ\Connector::getInstance();
            $q   = INSTANCE_ID . Rerouter::QUEUE_COMMAND;

            $mqc->declareQueue($q)
                ->bindQueue($q, '*.' . Notif::TOPIC_CMD_REROUTE);

            // redis
            $redisf = new Redis();
            $redisc = $redisf->getClientInstance();

            $qf1 = new QueueFactory();
            $qf1->setRedis($redisc)->setAMQP($amqp)->setKind(UserQueue::Q_TOSORT);
            // yeah we need two independent instances of this
            $qf2 = clone $qf1;

            // setup dependencies
            $bunnyCtrl  = new BunnyControl([
                'host'     => $conf->get('amqp_host'),
                'port'     => $conf->get('amqp_port'),
                'username' => $conf->get('amqp_user'),
                'password' => $conf->get('amqp_pass'),
                'vhost'    => $conf->get('amqp_vhost')
            ]);

            $binder     = new UserQueueBinder($amqp, $bunnyCtrl, $qf1, new Generator());
            $subscriber = new Subscriber($client, new UserSubscriptionList($qf2, $log));
            $strat      = new RerouterDispatchStrategy($opts['flags']);

            // throw all the above into the class we wanted in the first place
            $rerouter   = new Rerouter($loop, $strat, $subscriber, $log, $client, $binder, $bunnyCtrl);

            if ($opts['bind']) {
                $rerouter->onHangup();
            } else {
                $rerouter->onResume();
            }
        });

	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.", array($e));
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}
});

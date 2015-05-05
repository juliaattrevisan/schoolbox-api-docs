#!/usr/bin/php
<?php

use Alaress\Redis;
use Alaress\Scheduler\Cache\RedisCache;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;
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


$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Schedule', array(
	'verbose' => isset($opts['v']) ? $opts['v'] : null,
	'errors'  => E_ALL,
    'fields'  => array_values($fields),
    'flags'   => $flags
));

$sched = null;

$kernel->registerCustomShutdown(function() use (&$sched) {
	if(!$sched instanceof Scheduler) {
		return;
	}

	if($d = $sched->getDispatcher()) {
		$d->releaseWorkerPool();
	}
});

// CREATE SCHEDULER APP
$kernel->run(function($di, $loop, $log, $opts) use (&$sched) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var LoggerInterface $log
	 * @var array $opts
	 */

    // make sure our endpoint exists
    $mqc = \Schoolbox\Notif\MQ\Connector::getInstance();
    $q   = INSTANCE_ID . '.sb.sched';

    $mqc->declareQueue($q)
        ->bindQueue($q, '*.' . Notif::TOPIC_APPLICATION);

	try {
		$strat = new SchedulerDispatchStrategy(new Notif(), new QueueItemMapper(), $opts['flags']);
		$sched = new Scheduler($loop, $strat, $log, $q);

        $sched->setJitCache(new RedisCache(Redis::getClientInstance(), $log, INSTANCE_ID));

		// read STDIN in the stream loop so we can communicate with it in dev mode
		$stdin = new \React\Stream\Stream(STDIN, $loop);
		$stdin->on('data', function($data) use ($sched) {
			$sched->_stdincmd($data);
		});
	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.");
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}

});

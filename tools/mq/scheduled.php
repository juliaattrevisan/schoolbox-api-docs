#!/usr/bin/php
<?php

/**
 * The Schedule Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use Alaress\Redis\Async;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Postman;
use Alaress\Date\Clock;
use Alaress\Redis;
use Alaress\Scheduler\Cache\RedisCache;
use Alaress\Scheduler\Stomp\ResolutionManager;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Schedule;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;
use Schoolbox\Realtime\Schedule\Scheduler;
use Schoolbox\Realtime\Schedule\SchedulerDispatchStrategy;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Schedule', [Daemon\Kernel::OPTION_ERRORS => E_ALL,]);
$sched = null;

$kernel->registerCustomShutdown(function () use (&$sched) {
    if (!$sched instanceof Scheduler) {
        return;
    }

    if ($d = $sched->getDispatcher()) {
        $d->releaseWorkerPool();
    }
});

// CREATE SCHEDULER APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) use (&$scheduler) {

    /** @var Notif $notif */
    $notif = $di->get('notif');

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    /** @var IClient $stomp */
    $stomp = $di->get('stomp');

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.async');

    try {
        /** @var AMQPInterface $amqp */
        $amqp = $di->get('amqp');

        // we'll be using the schedule broker for receiving...
        $scheduleBroker = new Schedule\Broker(INSTANCE_ID, $amqp, $redis, $stomp);

        // ...and the route broker for sending
        $routeBroker    = new Routing\Broker(INSTANCE_ID, $amqp, $redis, $stomp);


        $postman        = new Postman($routeBroker, $log);

        $strat          = new SchedulerDispatchStrategy($notif, new QueueItemMapper(), $flags);
        $scheduler      = new Scheduler($loop, $strat, $log, new Clock(), $conf, new ResolutionManager(), $postman);

        $scheduler->setJitCache(new RedisCache(Redis::getClientInstance(), $log, INSTANCE_ID));


        // due to the async nature of STOMP, only register the consumer once the connection is actually established
        $stomp->connect()->then(function () use ($scheduleBroker, $scheduler, $log, $asyncRedis) {
            $log->info('Connected to STOMP', [ 'char' => '+' ]);

            $scheduleBroker->registerConsumer($scheduler);
            $log->debug('Consuming amqp://' . Schedule\Broker::QUEUE);

            $topic = 'scheduled.' . INSTANCE_ID . '.*';

            $asyncRedis->psubscribe($topic, $scheduler);
            $log->info("Listening on redis://$topic");
        });
    } catch (ConnectionException $e) {
        $log->alert('AMQP server is offline or STOMP misconfigured: ' . $e->getMessage(), [ 'exception' => $e ]);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to AMQP server: server is offline.');
        $notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert('RabbitMQ is offline');

        throw $e;
    }
});

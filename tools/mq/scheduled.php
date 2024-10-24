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
use Alaress\Async\Notification\Postman;
use Alaress\Date\Clock;
use Alaress\Scheduler\Cache\RedisCache;
use Alaress\Sentry\Tracing\Tracer;
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Schedule\Broker;
use Schoolbox\Realtime\Schedule\Scheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Schedule', [Daemon\Kernel::OPTION_ERRORS => E_ALL,]);

// CREATE SCHEDULER APP
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) use (&$scheduler) {
    /** @var Notif $notif */
    $notif = $di->get('notif');

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis.storage');

    /** @var IClient $stomp */
    $stomp = $di->get('stomp');

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.signalQueue.sub');

    try {
        /** @var \Schoolbox\Notif\MQ\Connector $amqp */
        $amqp = $di->get('amqp');

        // we'll be using the schedule broker for receiving...
        $tracer         = Tracer::getDefaultTrace()->setHub($di->get('sentry'));
        $scheduleBroker = new Broker(INSTANCE_ID, $amqp, $redis, $stomp, $tracer);

        /** @var Routing\Cache\Redis\ReadCache $subscriptionCache */
        $subscriptionCache = $di->get('subscriptionCache.readonly');

        $postman = new Postman($log, $subscriptionCache, $loop, $flags, INSTANCE_ID, $redis);

        $strat     = new DefaultDispatchStrategy($flags);
        $scheduler = new Scheduler($loop, $strat, $log, new Clock(), $conf, $postman, INSTANCE_ID);

        // restoring from redis cache
        $jit = new RedisCache($redis, $log, INSTANCE_ID);
        $scheduler->setJitCache($jit);
        $jit->restoreSchedule($amqp, Broker::QUEUE);

        // due to the async nature of STOMP, only register the consumer once the connection is actually established
        $stomp->connect()->then(function () use ($scheduleBroker, $scheduler, $log, $asyncRedis) {
            $log->info('Connected to STOMP', ['char' => '+']);

            $scheduleBroker->registerConsumer($scheduler);
            $log->debug('Consuming amqp://' . Broker::QUEUE);

            $topic = 'scheduled.' . INSTANCE_ID . '.*';

            $asyncRedis->psubscribe($topic, $scheduler);
            $log->info("Listening on redis://$topic");
        });
    } catch (ConnectionException $e) {
        $log->alert('AMQP server is offline or STOMP misconfigured: ' . $e->getMessage(), ['exception' => $e]);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to AMQP server: server is offline.');
        $notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert('RabbitMQ is offline');

        throw $e;
    }
});

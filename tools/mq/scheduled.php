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
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Schedule;
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
        $scheduleBroker = new Schedule\Broker(INSTANCE_ID, $amqp, $redis, $stomp);
        $dispatcher     = new \Alaress\WorkerCrew\Dispatcher(
            $loop,
            new Routing\DispatchResponder($log),
            $log,
            new Routing\DispatchStrategy($flags)
        );

        // ...and the route broker for sending
        $router      = new \Alaress\Routing\Router(new \Alaress\Routing\Dictionary\SubscriptionDictionary('.'));
        /** @var Routing\Cache\Redis\ReadCache $subscriptionCache */
        $subscriptionCache = $di->get('subscriptionCache.readonly');
        $transaction = new \Alaress\WorkerCrew\Transaction\DispatchTransaction(
            $loop,
            $dispatcher,
            Routing\DispatchStrategy::TASK_ROUTE
        );
        $txrx = RouteTXRX::buildTransmitter(new ListFactory(INSTANCE_ID, $redis));
        $postman = new Postman($log, $router, $transaction, $txrx, $subscriptionCache);

        $strat     = new DefaultDispatchStrategy($flags);
        $scheduler = new Scheduler($loop, $strat, $log, new Clock(), $conf, $postman);

        // restoring from redis cache
        $jit = new RedisCache($redis, $log, INSTANCE_ID);
        $scheduler->setJitCache($jit);
        $jit->restoreSchedule($amqp, Schedule\Broker::QUEUE);

        // due to the async nature of STOMP, only register the consumer once the connection is actually established
        $stomp->connect()->then(function () use ($scheduleBroker, $scheduler, $log, $asyncRedis) {
            $log->info('Connected to STOMP', ['char' => '+']);

            $scheduleBroker->registerConsumer($scheduler);
            $log->debug('Consuming amqp://' . Schedule\Broker::QUEUE);

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

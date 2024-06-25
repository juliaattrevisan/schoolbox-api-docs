#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use Alaress\Date\Clock;
use Alaress\Redis\Async;
use Alaress\Scheduler\Cache\RedisCache;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Mobile\Broker;
use Schoolbox\Realtime\Mobile\Scheduler;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Mobile App Delivery', []);

// Create the mobile app delivery daemon.
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {
    /** @var Notif $notif */
    $notif = $di->get('notif');

    $clock = new Clock();

    /** @var ClientInterface $redis */
    $redis = $di->get('redis.storage');

    /** @var IClient $stomp */
    $stomp = $di->get('stomp');

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.signalQueue.sub');

    try {
        /** @var \Schoolbox\Notif\MQ\Connector $amqp */
        $amqp = $di->get('amqp');

        // we'll be using the mobile broker for receiving...
        $broker = new Broker(INSTANCE_ID, $amqp, $redis, $stomp);


        // Need a new strat for listening to mobile queue?
        $strat     = new DefaultDispatchStrategy($flags);
        $scheduler = new Scheduler($loop, $strat, $log, $clock);


        // TODO restore from redis cache
//        $jit = new RedisCache($redis, $log, INSTANCE_ID);
//        $scheduler->setJitCache($jit);
//        $jit->restoreSchedule($amqp, Broker::QUEUE);

        // due to the async nature of STOMP, only register the consumer once the connection is actually established
        $stomp->connect()->then(function () use ($broker, $scheduler, $log, $asyncRedis) {
            $log->info('Connected to STOMP', ['char' => '+']);

            $broker->registerConsumer($scheduler);
            $log->debug('Consuming amqp://' . Broker::QUEUE);

            $topic = 'mobiled.' . INSTANCE_ID . '.*';
            $asyncRedis->psubscribe($topic, $scheduler);
            $log->info("Listening on signal queue: redis://$topic");
        });
    } catch (ConnectionException $e) {
        $log->alert('AMQP server is offline or STOMP misconfigured: ' . $e->getMessage(), ['exception' => $e]);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to AMQP server: server is offline.');
        $notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert('RabbitMQ is offline');

        throw $e;
    }
});

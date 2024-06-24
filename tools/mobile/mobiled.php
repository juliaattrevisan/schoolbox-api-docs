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
    $instance = INSTANCE_ID;
    $topic    = "mobile.$instance.*";

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

        // and then do something or other with it here
    } catch (ConnectionException $e) {
        $log->alert('AMQP server is offline or STOMP misconfigured: ' . $e->getMessage(), ['exception' => $e]);
    } catch (AMQPRuntimeException $e) {
        $log->alert('Unable to connect to AMQP server: server is offline.');
        $notif->setTopic(Notif::TOPIC_NOTIFICATION)->alert('RabbitMQ is offline');

        throw $e;
    }

    // Need a new strat for listening to mobile queue?
    $strat      = new DefaultDispatchStrategy($flags);

    $scheduler = new Scheduler($loop, $strat, $log, $clock);
    $asyncRedis->psubscribe($topic, $scheduler);
    $log->info("Listening on redis://$topic");
});

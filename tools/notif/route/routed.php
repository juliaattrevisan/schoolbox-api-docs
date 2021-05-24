#!/usr/bin/php
<?php

/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\Stomp\IClient;
use Alaress\Redis\Async;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Transaction\DispatchTransaction;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\DispatchResponder;
use Schoolbox\Realtime\Routing\DispatchStrategy;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel('Routing', [Daemon\Kernel::OPTION_ERRORS => E_ALL,]);

// ROUTING APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {

    $di->get('stomp')->connect()->then(function (IClient $client) use ($di, $loop, $log, $opts, $flags) {
        try {
            $log->info('Connected to STOMP', [ 'char' => '+' ]);

            // the list of instances this particular daemon will listen for
            $instances = [ INSTANCE_ID ];

            /** @var ClientInterface $redis */
            $redis = $di->get('redis');

            /** @var AMQPInterface $amqp */
            $amqp = $di->get('amqp');

            // also listen for signals
            /** @var Async\Client $asyncRedis */
            $asyncRedis = $di->get('redis.async');
            $dispatcher = new Dispatcher($loop, new DispatchResponder($log), $log, new DispatchStrategy($flags));

            $cache = new Routing\Cache\Redis\ReadCache($redis, $log);

            // ensure endpoints exist
            foreach ($instances as $instance) {
                $txrx = RouteTXRX::buildTransmitter(new ListFactory($instance, $redis));

                // this holds and traverses all our subscription data
                $router = new Router(new SubscriptionDictionary('.'));
                $routeBroker = new Routing\Broker($instance, $amqp, $redis, $client);

                $q = $routeBroker->getQueue();
                $t = "routed.$instance.*";

                $amqp->declareQueue($q);

                $cache->readIntoRouter($instance, $router);

                $transaction = new DispatchTransaction(
                    $loop,
                    $dispatcher,
                    DispatchStrategy::TASK_ROUTE,
                    Subscription\Handler::TRANSACTION_CACHE_TIMEOUT,
                    Subscription\Handler::TRANSACTION_COUNT_LIMIT
                );

                $handler = new Subscription\Handler(new Subscription\Resolver($router), $log, $txrx, $transaction, $cache);

                $routeBroker->registerConsumer($handler);
                $log->info("Consuming amqp://$q");

                $asyncRedis->psubscribe($t, $handler);
                $log->info("Listening on redis://$t");
            }
        } catch (\Throwable $e) {
            $loop->stop();
            $log->alert($e->getMessage(), [ 'exception' => $e ]);
            throw $e;
        }
    });
});

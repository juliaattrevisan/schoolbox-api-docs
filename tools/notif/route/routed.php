#!/usr/bin/php
<?php
/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Redis\Async;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Transaction\DispatchTransaction;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\DispatchResponder;
use Schoolbox\Realtime\Routing\DispatchStrategy;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription;

$flags = implode(' ', array_slice($argv, 1));
$opts = require __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'flags'   => $flags,
]);


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    $di->get('stomp')->connect()->then(function (Client $client) use ($di, $loop, $log, $opts) {
        try {
            $log->info('Connected to STOMP', [ 'char' => '+' ]);

            // the list of instances this particular daemon will listen for
            $instances = [ INSTANCE_ID ];

            // daemon-version: no dependencies required
            $txrx = new RouteTXRX();

            /** @var ClientInterface $redis */
            $redis = $di->get('redis');

            /** @var AMQPInterface $amqp */
            $amqp = $di->get('amqp');

            // also listen for signals
            $asyncRedis  = new Async\Client($di->get('config')->get('redis_url'), $loop);
            $dispatcher  = new Dispatcher($loop, new DispatchResponder($log), $log, new DispatchStrategy($opts['flags']));

            $cache = new Routing\Cache\Redis\ReadCache($redis, $log);

            // ensure endpoints exist
            foreach ($instances as $instance) {
                // this holds and traverses all our subscription data
                $router = new Router(new SubscriptionDictionary('.'));
                $routeBroker = new Routing\Broker($instance, $amqp, $redis, $client);

                $q = $routeBroker->getQueue();
                $t = "routed.$instance.*";

                $amqp->declareQueue($q);

                $cache->readIntoRouter($instance, $router);

                $transaction = new DispatchTransaction($loop, $dispatcher, DispatchStrategy::TASK_ROUTE,
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
            $log->alert($e->getMessage(), [ $e ]);
            throw $e;
        }
    });
});

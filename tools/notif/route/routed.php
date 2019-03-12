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
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\DispatchResponder;
use Schoolbox\Realtime\Routing\DispatchStrategy;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\Application\Daemon;
use Alaress\Lumberjack\Logger;

require __DIR__ . '/../../../conf/core/require.php';

$fields = Logger::getAvailableFields();
$opts   = sb_getDaemonOpt();

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

$kernel = new Daemon\Kernel('Routing', [
    Daemon\Kernel::OPT_VERBOSE => isset($opts['v']) ? $opts['v'] : null,
    Daemon\Kernel::OPT_ERRORS  => E_ALL,
    Daemon\Kernel::OPT_FIELDS  => array_keys($fields),
    'flags'                    => $flags,
]);


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $di->get('stomp')->connect()->then(function (Client $client) use ($di, $loop, $log, $opts) {
        try {
            $log->info('Connected to STOMP', [ 'char' => '+' ]);

            // the list of instances this particular daemon will listen for
            $instances = [ INSTANCE_ID ];

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
                $txrx = RouteTXRX::buildTransmitter(new ListFactory($instance, $redis));

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
            $log->alert($e->getMessage(), [ 'exception'=>$e ]);
            throw $e;
        }
    });
});

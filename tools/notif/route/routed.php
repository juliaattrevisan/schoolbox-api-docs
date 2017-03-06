#!/usr/bin/php
<?php
/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Transaction\DispatchTransaction;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Realtime\Routing\DispatchStrategy;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription;

$flags = implode(' ', array_slice($argv, 1));
$opts = require __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'flags'   => $flags,
));


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    $di->get('stomp')->connect()->then(function (Client $client) use ($di, $loop, $log, $opts) {

        // the list of instances this particular daemon will listen for
        $instances = [ INSTANCE_ID ];

        // ensure endpoints exist
        foreach ($instances as $instance) {
            $r = '*.notify.#';
            $q = "$instance.route";
            $x = "sb.$instance.tx";
            // TODO make the queues and exchanges instance-agnostic
            $log->info("Ensuring binding exists: '$x' -> ($r) -> '$q'", [ 'char' => '>' ]);
            Connector::getInstance()->declareQueue($q)->bindQueue($q, $r, $x);
        }

        // this holds and traverses all our subscription data
        $router = new Router(new SubscriptionDictionary('.'));

        /** @var Redis|ClientInterface $redis */
        $redis = $di->get('redis');

        (new Subscription\Loader\Factory($redis, $log))->get('m:vagrantx:routes')->loadSubscriptions($router);

        // daemon-version: no dependencies required
        $txrx = new RouteTXRX();

        $handler     = new Subscription\Handler($router, $client, $log, $txrx);
        $dispatcher  = new Dispatcher($loop, $handler, $log, new DispatchStrategy($opts['flags']));
        $transaction = new DispatchTransaction($loop, $dispatcher, 'route',
            Subscription\Handler::TRANSACTION_CACHE_TIMEOUT,
            Subscription\Handler::TRANSACTION_COUNT_LIMIT
        );

        foreach ($instances as $instance) {
            $handler->listen($instance, $transaction);
        }
    });
});

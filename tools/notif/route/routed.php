#!/usr/bin/php
<?php
/**
 * The Routing Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Routing\Router;
use Alaress\WorkerCrew\Dispatcher;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Realtime\Routing\DispatchStrategy;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\UserMapper;

$flags = implode(' ', array_slice($argv, 1));
$opts = require_once __DIR__ . '/../../../conf/core/require-cli.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Routing', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'flags'   => $flags,
));


// ROUTING APP
$kernel->run(function(Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {

    $di->get('stomp')->connect()->then(function (Client $client) use ($loop, $log, $opts) {

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
        $router = new Router('.');

        // skipping this step is not advised ;)
        (new Subscription\Loader($router, $log))->loadSubscriptions(new Generator(/* just use defaults... */), UserMapper::getInstance());

        // subscribe to the
        $handler    = new Subscription\Handler(new Subscription\Manager($router), $client, $log);

        $dispatcher = new Dispatcher($loop, $handler, $log, new DispatchStrategy($opts['flags']));

        foreach ($instances as $instance) {
            $handler->listen($instance, $loop, $dispatcher);
        }
    });
});

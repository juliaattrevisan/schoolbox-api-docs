#!/usr/bin/php
<?php
/**
 *
 * This script is solely responsible for calculating the set of bindings FOR A SPECIFIED INSTANCE.
 * It will:
 *  - calculate ALL bindings for the instance
 *  - cache the set, replacing any previously cached set
 *  - broadcast a SIGHUP (which advises `routed` instances to reload from cache)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress\Routing\FlatRouter;
use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\UserMapper;

$opts = require_once __DIR__ . '/../../../conf/core/require-cli.php';

// this script requires instance ID to be explicitly set
if (empty($opts['instance'])) {
    die ("[!] You must specify a target instance with --instance=INSTANCE_ID\n");
}

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Binding Calculator', [
    'verbose'  => isset($opts['v']) ? $opts['v'] : null,
    'fields'   => array_keys($opts),
    'instance' => $opts['instance']
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $instanceId = $opts['instance'];

    $log->warning("Running on $instanceId");

    $router = new FlatRouter();

    // this will calculate and load all subscriptions into the router
    (new Subscription\Loader\BindingLoader(new Generator(/* just use defaults... */), UserMapper::getInstance(), $log))->loadSubscriptions($router);

    $json = json_encode($router->getAll());
    $gz = gzcompress($json, 9);

    $log->info(sprintf("Compressing route tree: %s => %s", Alaress_Byte_Format::bytesToString(strlen($json)), Alaress_Byte_Format::bytesToString(strlen($gz))));

    $routeStorageKey = "m:$instanceId:routes";

    /** @var \Redis $redis */
    $redis = $di->get('redis');

    $redis->set($routeStorageKey, $gz);

    $log->info("Route tree stored in `$routeStorageKey`", [ 'char' => '+' ]);

    $reloadChannel = "route.$instanceId";

    $log->info("Broadcasting SIGHUP on `$reloadChannel`", [ 'char' => '>' ]);

    $redis->publish($reloadChannel, 'SIGHUP');
});


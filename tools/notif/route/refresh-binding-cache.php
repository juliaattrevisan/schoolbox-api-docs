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
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Realtime\Routing\Subscription;
use Schoolbox\UserMapper;

$fields = [ 'userId:' ];

$opts = require_once __DIR__ . '/../../../conf/core/require-cli.php';

// this script requires instance ID to be explicitly set
if (empty($opts['instance'])) {
    die ("[!] You must specify a target instance with --instance=INSTANCE_ID\n");
}

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Binding Calculator', [
    'verbose'  => isset($opts['v']) ? $opts['v'] : null,
    'fields'   => array_keys($opts),
    'instance' => $opts['instance'],
    'userId'   => isset($opts['userId']) ? $opts['userId'] : null
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $instanceId = $opts['instance'];

    $log->warning("Running on $instanceId");

    $router = new FlatRouter();

    $generator = new Generator(/* just use defaults... */);
    $routeStorageKey = "m:$instanceId:routes";

    if ($opts['userId']) {
        $userId = $opts['userId'];
        // we're affecting just one user here

        /** @var ClientInterface $redis */
        $redis = $di->get('redis');

        // TODO SEMAPHORE!!!
        $log->error("[TODO] Cache lock obtained");

        // start by loading from cache
        (new Subscription\Loader\Factory($redis, $log))->get($routeStorageKey)->loadSubscriptions($router);

        // use the generator to compute new bindings for the user
        $userBindings = $generator->generateBindingsForUser(UserMapper::getInstance()->findById($userId));

        // extract the list and reset the router
        $allRoutes = $router->getAll();
        $router = new FlatRouter();

        // compile user routes
        $userRoutes  = [];
        $changeCount = 0;

        foreach ($userBindings as $prefix => $data) {
            foreach ($data as $target) {
                $binding = "*.notify.$prefix.$target";
                $userRoutes[$binding] = true;
            }
        }

        // remove any bindings no longer applicable to this user
        foreach ($allRoutes as $path => $pathData) {
            if (isset($pathData[$userId])) {
                if (empty($userRoutes[$path])) {
                    $log->info("User $userId - `$path`", [ 'char' => '-' ]);
                    unset ($allRoutes[$path][$userId]);
                    ++$changeCount;
                }
            }
        }

        // reinsert all remaining routes
        foreach ($allRoutes as $path => $pathData) {
            foreach ($pathData as $key => $value) {
                $router->add($path, $key, $value);
            }
        }

        // overlay this user's bindings to account for any new ones
        foreach (array_keys($userRoutes) as $path) {
            $a = count($router->getAll());
            $router->add($path, $userId, true);
            $b = count($router->getAll());
            if ($b != $a) {
                $log->info("User $userId + `$path`", [ 'char' => '+' ]);
                ++$changeCount;
            }
        }

        if ($changeCount == 0) {
            $log->notice("No changes made to bindings for user $userId");
            // TODO release semaphore
            $log->error("[TODO] Cache lock released");
            return;
        }

    } else {
        // this will calculate and load all subscriptions into the router
        (new Subscription\Loader\BindingLoader($generator, UserMapper::getInstance(), $log))->loadSubscriptions($router);
    }

    $json = json_encode($router->getAll());
    $gz = gzcompress($json, 9);

    $log->info(sprintf("Compressing route tree: %s => %s", Alaress_Byte_Format::bytesToString(strlen($json)), Alaress_Byte_Format::bytesToString(strlen($gz))));


    /** @var \Redis $redis */
    $redis = $di->get('redis');

    $redis->set($routeStorageKey, $gz);

    if ($opts['userId']) {
        // TODO release semaphore
        $log->error("[TODO] Cache lock released");
    }

    $log->info("Route tree stored in `$routeStorageKey`", [ 'char' => '+' ]);

    $reloadChannel = "route.$instanceId";

    $log->info("Broadcasting SIGHUP on `$reloadChannel`", [ 'char' => '>' ]);

    $redis->publish($reloadChannel, 'SIGHUP');
});


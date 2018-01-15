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

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;

$fields = [ 'userId:' ];

$opts = require __DIR__ . '/../../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Binding Calculator', [
    'verbose'  => isset($opts['v']) ? $opts['v'] : null,
    'fields'   => array_keys($opts),
    'instance' => isset($opts['instance']) ? $opts['instance'] : null,
    'userId'   => isset($opts['userId']) ? $opts['userId'] : null
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    // If no instance ID is provided, fall back to default if it exists
    if(!isset($opts['instance'])) {
        $opts['instance'] = INSTANCE_ID;
    }

    /** @var WritableCache $cache */
    $cache = $di->get('subscriptionCache');

    if ($opts['userId']) {
        $cache->cacheUsers($opts['instance'], [$opts['userId']]);

    } else {
        $cache->cacheAllUsers($opts['instance']);
    }

});

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
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Binding Calculator',
    [
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['userId:', 'instance:'],
    ]
);

$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    // If no instance ID is provided, fall back to default if it exists
    $instanceId = $opts['instance'] ?? INSTANCE_ID;
    $userId     = $opts['userId'] ?? null;

    /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
    $cache = $di->get('subscriptionCache');
    $cache->setLog($log);

    if ($userId) {
        $cache->cacheUsers($instanceId, [$userId]);
    } else {
        $cache->cacheAllUsers($instanceId);
    }
});

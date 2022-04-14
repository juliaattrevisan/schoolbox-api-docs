#!/usr/bin/php
<?php

/**
 * Flush the route cache users queue.
 *
 * It's possible for the queue of users to refresh routes for to get into a
 * state where it can never clear. In particular, this will happen if an
 * exception gets thrown out of the refresh users loop while not all users have
 * been refreshed; if this happens, then subsequent calls to process the queue
 * will always abort, because it will refuse to start processing the queue if
 * it isn't empty.
 *
 * Use this script to flush that queue manually.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use Schoolbox\Application\Daemon;

require __DIR__ . '/../../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Flush Cache Users Queue',
    [
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['instance:'],
    ]
);

$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts) {
    // If no instance ID is provided, fall back to default if it exists
    $instanceId = $opts['instance'] ?? INSTANCE_ID;

    /** @var \Schoolbox\Realtime\Routing\Cache\Redis\WriteCache $cache */
    $cache = $di->get('subscriptionCache');
    $cache->setLog($log);
    $cache->flushCacheUsersQueue($instanceId);
});

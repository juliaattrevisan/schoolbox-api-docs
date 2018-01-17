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
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Routing\Cache\WritableCache;

$opts = require __DIR__ . '/../../../conf/core/require-cli.php';

spl_autoload_call('\\Schoolbox\\Application\\Daemon');
$kernel = new Schoolbox\Application\Daemon\Kernel('Flush Cache Users Queue', [
    'verbose'  => isset($opts['v']) ? $opts['v'] : null,
    'instance' => isset($opts['instance']) ? $opts['instance'] : null,
]);

$kernel->run(function(Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {
    // If no instance ID is provided, fall back to default if it exists
    if(!isset($opts['instance'])) {
        $opts['instance'] = INSTANCE_ID;
    }

    /** @var WritableCache $cache */
    $cache = $di->get('subscriptionCache');
    $cache->flushCacheUsersQueue($opts['instance']);
});

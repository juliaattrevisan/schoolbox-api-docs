<?php

/**
 * Put a whole heap of junk keys into Redis. The keys all have a similar name
 * to notifications
 *
 * This may be useful for load testing purposes, particularly around the
 * retraction of notifications.
 */

use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;

$shortOpts = 'c:x:';
$fields    = ['perform'];

$opts = require __DIR__ . '/../../conf/core/require-cli.php';

$kernel = new Schoolbox\Application\Daemon\Kernel('Redis Notif Messup', [
    'verbose' => $opts['v'] ?? null,
    'errors'  => E_ALL,
    'fields'  => array_keys($opts),
    'count'   => max(1, $opts['c'] ?? 10000),
    'expiry'  => max(1, $opts['x'] ?? 3600),
]);

$kernel->run(function (Di\Container $di, LoopInterface $loop, Logger $log, array $opts) {
    $redis  = \Alaress\Redis::getClientInstance();
    $count  = (int)$opts['count'];
    $expiry = (int)$opts['expiry'];
    $log->notice(sprintf('Creating %d junk keys with expiry %d seconds...', $count, $expiry));
    for ($i = 1; $i <= $count; $i++) {
        $redis->setex(sprintf('msg:%s:e:%d', INSTANCE_ID, $i), $expiry, '');
    }
});

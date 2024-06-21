#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Date\Clock;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\Realtime\Mobile\Scheduler;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Mobile App Delivery', []);

// Create the mobile app delivery daemon.
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {
    $instance = INSTANCE_ID;
    $topic    = "mobile.$instance.*";

    $clock = new Clock();

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.signalQueue.sub');
    $strat      = new DefaultDispatchStrategy($flags);

    $scheduler = new Scheduler($loop, $strat, $log, $clock);
    $asyncRedis->psubscribe($topic, $scheduler);
    $log->info("Listening on redis://$topic");
});

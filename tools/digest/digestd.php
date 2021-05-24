#!/usr/bin/php
<?php

use Alaress\Date\Clock;
use Alaress\Redis\Async;
use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\RoleMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Digest\DigestScheduler;
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Schedule\Trigger\Factory\DigestTriggerFactory;
use Schoolbox\UserMapper;

/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Digest',
    [
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['debug-interval:'],
    ]
);

// CREATE DIGEST APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) {

    $instance      = INSTANCE_ID;
    $topic         = "digestd.$instance.*";
    $debugInterval = $opts['debug-interval'] ?? null;

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $clock = new Clock();

    $roleMapper = $di->get('mapperFactory')->get(RoleMapper::class);
    $userMapper = $di->get('mapperFactory')->get(UserMapper::class);

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.async');
    $strat      = new DefaultDispatchStrategy($flags);
    $notifier   = new MailNotifier(new ListFactory(INSTANCE_ID, $redis), $userMapper, $conf, $log);
    $factory    = new DigestTriggerFactory($notifier, $clock, $debugInterval);

    $scheduler  = new DigestScheduler($loop, $strat, $log, $clock, $roleMapper, $conf, $factory);

    $asyncRedis->psubscribe($topic, $scheduler);

    $log->info("Listening on redis://$topic");
});

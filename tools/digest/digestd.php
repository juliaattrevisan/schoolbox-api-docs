#!/usr/bin/env php
<?php

/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Date\Clock;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Predis\ClientInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon;
use Schoolbox\RoleMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Digest\DigestScheduler;
use Schoolbox\Realtime\Digest\MailNotifier;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Schedule\Trigger\Factory\DigestTriggerFactory;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Digest',
    [
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'c::',
        Daemon\Kernel::OPTION_APP_LONG_OPTS => ['debug-interval:'],
    ],
);

// CREATE DIGEST APP
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags): void {

    $instance      = INSTANCE_ID;
    $topic         = "digestd.$instance.*";
    $debugInterval = $opts['debug-interval'] ?? null;

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis.storage');

    $configFile   = $opts['c'] ?? (SITE_root . '/tools/digest/digestd.conf');
    $stratBuilder = new ConfiguredDispatchStrategyBuilder(new ConfigurationReader($configFile)->readConfig());
    $strat        = $stratBuilder->build($flags);

    /** @var \Schoolbox\Mail\EmailFactory $emailFactory */
    $emailFactory = $di->get('mail.emailFactory');
    $userMapper   = $di->get('mapperFactory')->get(UserMapper::class);
    $notifier     = new MailNotifier(new ListFactory(INSTANCE_ID, $redis), $userMapper, $emailFactory, $conf, $log);

    $clock      = new Clock();
    $roleMapper = $di->get('mapperFactory')->get(RoleMapper::class);
    $factory    = new DigestTriggerFactory($notifier, $clock, $debugInterval);
    $scheduler  = new DigestScheduler($loop, $strat, $log, $clock, $roleMapper, $conf, $factory);

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.signalQueue.sub');
    $asyncRedis->psubscribe($topic, $scheduler);

    $log->info("Listening on redis://$topic");

    $loop->addPeriodicTimer(
        $strat->getMetricInterval() / 1000,
        fn () => $log->info(json_encode($scheduler->getStats())),
    );
});

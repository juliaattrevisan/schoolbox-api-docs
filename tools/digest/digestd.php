#!/usr/bin/php
<?php

use Alaress\Date\Clock;
use Alaress\Lumberjack\Logger;
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
use Schoolbox\Application\Daemon;

/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../conf/core/require.php';

$opts   = sb_getDaemonOpt(['debug-interval:']);
$fields = sb_getDaemonLoggerFields($opts);
$flags  = implode(' ', array_slice($argv, 1));

$kernel = new Daemon\Kernel('Digest', [
    Daemon\Kernel::OPT_VERBOSE => isset($opts['v']) ? $opts['v'] : null,
    Daemon\Kernel::OPT_FIELDS  => $fields,
    'flags'                    => $flags,
    'debug-interval'           => isset($opts['debug-interval']) ? $opts['debug-interval'] : null
]);



// CREATE DIGEST APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    $instance = INSTANCE_ID;
    $topic    = "digestd.$instance.*";

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $clock = new Clock();

    $roleMapper = $di->get('mapperFactory')->get(RoleMapper::class);
    $userMapper = $di->get('mapperFactory')->get(UserMapper::class);
    $asyncRedis = new Async\Client($di->get('config')->get('redis_url'), $loop);
    $strat      = new DefaultDispatchStrategy($opts['flags']);
    $notifier   = new MailNotifier(new ListFactory(INSTANCE_ID, $redis), $userMapper, $conf, $log );
    $factory    = new DigestTriggerFactory($notifier, $clock, $opts['debug-interval']);

    $scheduler  = new DigestScheduler($loop, $strat, $log, $clock, $roleMapper, $conf, $factory);

    $asyncRedis->psubscribe($topic, $scheduler);

    $log->info("Listening on redis://$topic");
});

#!/usr/bin/php
<?php

use Alaress\Date\Clock;
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
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = [ 'porcelain', 'mem', 'truemem', 'pid', 'debug-interval:' ];

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest', [
	'verbose'        => isset($opts['v']) ? $opts['v'] : null,
    'fields'         => array_values($fields),
    'flags'          => $flags,
    'debug-interval' => isset($opts['debug-interval']) ? $opts['debug-interval'] : null
]);



// CREATE DIGEST APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    /** @var ClientInterface $redis */
    $redis = $di->get('redis');

    $clock = new Clock();

    $roleMapper = $di->get('mapperFactory')->get(RoleMapper::class);
    $userMapper = $di->get('mapperFactory')->get(UserMapper::class);
    $strat      = new DefaultDispatchStrategy($opts['flags']);
    $notifier   = new MailNotifier(new ListFactory(INSTANCE_ID, $redis), $userMapper);
    $factory    = new DigestTriggerFactory($notifier, $clock, $opts['debug-interval']);

    new DigestScheduler($loop, $strat, $log, $clock, $roleMapper, $conf, $factory);
});

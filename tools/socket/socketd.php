#!/usr/bin/php
<?php

use Alaress\Database\Connector\IConnector;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use Schoolbox\Application\Daemon\Kernel;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\HttpServer;
use Schoolbox\Realtime\Pipe\PipeManagerFactory;
use Schoolbox\Realtime\Pipe\Server;
use Schoolbox\Application\Daemon;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../conf/core/require.php';

$opts   = sb_getDaemonOpt([], 'p::c::');
$fields = sb_getDaemonLoggerFields($opts);
$flags  = implode(' ', array_slice($argv, 1));

$kernel = new Daemon\Kernel('Socket', [
    Daemon\Kernel::OPT_VERBOSE => isset($opts['v']) ? $opts['v'] : null,
    Daemon\Kernel::OPT_FIELDS  => $fields,
    'port'                     => empty($opts['p']) ? 9081 : $opts['p'],
    'conf'                     => empty($opts['c']) ? 'socketd.conf' : $opts['c'],
    'flags'                    => $flags
]);

// CREATE WEBSOCKET APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) use ($kernel) {

    /** @var Redis|ClientInterface $redis */
    $redis = \Alaress\Redis::getClientInstance();

    // TODO put this in DI
    $asyncRedis = new Async\Client($di->get('config')->get('redis_url'), $loop);

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    $stratBuilder = new ConfiguredDispatchStrategyBuilder((new ConfigurationReader($opts['conf']))->readConfig());

    $delim = in_array('porcelain', $opts[ 'fields' ], false) ? ' ' : "\n    ";

    $log->info(implode($delim, [ 'Using strategy:' ] + $stratBuilder->summarise()), [ 'char' => '+' ]);

    $pipeManager = (new PipeManagerFactory(
        $loop, new PhpHandler(), $log, $redis, $asyncRedis, $conf->get('session_length'),
        $db, INSTANCE_ID, $stratBuilder->build($opts['flags']), $kernel->getHardLimit(Kernel::RLIMIT_FILE_DESCRIPTORS)
    ))->build();

    $wss = new Ratchet\WebSocket\WsServer($pipeManager);
    $wss->disableVersion(0);

    $https   = new HttpServer($wss);
    $webSock = new Server($loop);

    try {
        $webSock->listen($opts['port'], '0.0.0.0');
        $log->info("Listening for incoming websockets on port {$opts['port']}...", [ 'char' => '+' ]);

    } catch (ConnectionException $e) {
        $log->alert('Unable to launch: ' . $e->getMessage(), [ 'exception'=>$e ]);
        throw $e;
    }

    new Ratchet\Server\IoServer($https, $webSock, $loop);
});



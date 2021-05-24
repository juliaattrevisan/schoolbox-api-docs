#!/usr/bin/php
<?php

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Server\IoServer;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\Server as SocketServer;
use Schoolbox\Application\Daemon;
use Schoolbox\Application\Daemon\Kernel;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\HttpServer;
use Schoolbox\Realtime\Pipe\PipeManagerFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Socket', [Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'p::c::']);

// CREATE WEBSOCKET APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) use ($kernel) {

    $port = $opts['p'] ?? 9081;
    $conf = $opts['c'] ?? 'socketd.conf';

    /** @var Redis|ClientInterface $redis */
    $redis = \Alaress\Redis::getClientInstance();

    /** @var IConfigLoader $config */
    $config = $di->get('config');

    /** @var Async\Client $asyncRedis */
    $asyncRedis = $di->get('redis.async');

    /** @var IConnector $db */
    $db = $di->get('databaseInternal');

    $stratBuilder = new ConfiguredDispatchStrategyBuilder((new ConfigurationReader($conf))->readConfig());

    $delim = \array_key_exists(Daemon\Kernel::OPT_LONG_PORCELAIN, $opts) ? ' ' : "\n    ";

    $log->info(implode($delim, ['Using strategy:'] + $stratBuilder->summarise()), ['char' => '+']);

    $pipeManager = (new PipeManagerFactory(
        $loop,
        new PhpHandler(),
        $log,
        $redis,
        $asyncRedis,
        $config->get('session_length'),
        $db,
        INSTANCE_ID,
        $stratBuilder->build($flags),
        $kernel->getHardLimit(Kernel::RLIMIT_FILE_DESCRIPTORS)
    ))->build();

    $wss = new Ratchet\WebSocket\WsServer($pipeManager);
    $wss->enableKeepAlive($loop);
    $https = new HttpServer($wss);

    // IOServer::factory cannot be used because it creates a new event loop,
    // which is never started.
    // Create the server manually.
    $socket = new SocketServer('0.0.0.0:' . $port, $loop);
    $server = new IoServer($https, $socket, $loop);

    $log->info("Listening for incoming websockets on port {$port}...", ['char' => '+']);
    $server->run();
//    try {
//        $server->run();
//        $log->info("Listening for incoming websockets on port {$port}...", [ 'char' => '+' ]);
//
//    } catch (ConnectionException $e) {
//        $log->alert('Unable to launch: ' . $e->getMessage(), [ 'exception'=>$e ]);
//        throw $e;
//    }
});

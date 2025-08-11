#!/usr/bin/php
<?php

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Redis;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Server\IoServer;
use Ratchet\Session\Serialize\HandlerInterface;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Schoolbox\Application\Daemon;
use Schoolbox\Application\Daemon\Kernel;
use Schoolbox\MultiInstance;
use Schoolbox\Realtime\Pipe\HttpServer;
use Schoolbox\Realtime\Pipe\PipeManagerFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel('Socket', [Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'p::c::']);

// CREATE WEBSOCKET APP
$kernel->run(function (ContainerInterface $di, LoopInterface $loop, LoggerInterface $log, array $opts, string $flags) use ($kernel): void {

    $port = $opts['p'] ?? 9081;
    $conf = $opts['c'] ?? 'socketd.conf';


    // Need the multi-instance repository
    /** @var MultiInstance\InstanceRepository $instanceRepository */
    $instanceRepository = $di->get('multiInstance.instanceRepository');

    // Config will always be per-instance
    /** @var MultiInstance\ConfigRepository $configRepository */
    $configRepository = $di->get('multiInstance.configRepository');

    // Database will always be per-instance
    /** @var MultiInstance\ConnectorRepository $connectorRepository */
    $connectorRepository = $di->get('multiInstance.connectorRepository');

    // Need the multi-instance Redis repositories
    /** @var MultiInstance\RedisClientRepository $redisClientRepository */
    $redisClientRepository = $di->get('multiInstance.redisStorageClientRepository');

    /** @var MultiInstance\RedisAsyncClientRepository $redisAsyncClientRepository */
    $redisAsyncClientRepository = $di->get('multiInstance.redisAsyncClientRepository');

    $stratBuilder = new ConfiguredDispatchStrategyBuilder((new ConfigurationReader($conf))->readConfig());

    $delim = \array_key_exists(Daemon\Kernel::OPT_LONG_PORCELAIN, $opts) ? ' ' : "\n    ";

    $log->info(implode($delim, ['Using strategy:'] + $stratBuilder->summarise()), ['char' => '+']);

    $pipeManager = (new PipeManagerFactory(
        $loop,
        new class implements HandlerInterface {
            public function serialize(array $data): string
            {
                return serialize($data);
            }

            public function unserialize($raw)
            {
                return unserialize($raw);
            }
        },
        $log,
        $instanceRepository,
        $configRepository,
        $connectorRepository,
        $redisClientRepository,
        $redisAsyncClientRepository,
        $stratBuilder->build($flags),
        $kernel->getHardLimit(Kernel::RLIMIT_FILE_DESCRIPTORS),
    ))->build();

    $wss = new Ratchet\WebSocket\WsServer($pipeManager);
    $wss->enableKeepAlive($loop);
    $https = new HttpServer($wss);

    // IOServer::factory cannot be used because it creates a new event loop,
    // which is never started.
    // Create the server manually.
    $socket = new SocketServer('0.0.0.0:' . $port, [], $loop);
    $server = new IoServer($https, $socket, $loop);

    $log->info("Listening for incoming websockets on port {$port}...", ['char' => '+']);
    $server->run();
});

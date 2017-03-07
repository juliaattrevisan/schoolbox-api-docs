#!/usr/bin/hhvm
<?php

use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\HttpServer;
use Schoolbox\Realtime\Pipe\PipeManagerFactory;
use Schoolbox\Realtime\Pipe\Server;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = [ 'porcelain', 'mem', 'truemem', 'pid' ];

$opts = getopt('vp::c::', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Socket', [
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'port'    => empty($opts['p']) ? 9081 : $opts['p'],
    'conf'    => empty($opts['c']) ? 'socketd.conf' : $opts['c'],
    'fields'  => array_values($fields),
    'flags'   => $flags
]);

// CREATE WEBSOCKET APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    /** @var Redis|ClientInterface $redis */
    $redis = \Alaress\Redis::getClientInstance();

    // TODO put this in DI
    $asyncRedis = new Async\Client($di->get('config')->get('redis_url'), $loop);

    /** @var Database $db */
    $db = $di->get('database');

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    $stratBuilder = new ConfiguredDispatchStrategyBuilder((new ConfigurationReader($opts['conf']))->readConfig());

    $delim = in_array('porcelain', $opts[ 'fields' ], false) ? ' ' : "\n    ";

    $log->info(implode($delim, [ 'Using strategy:' ] + $stratBuilder->summarise()), [ 'char' => '+' ]);

    $pipeManager = (new PipeManagerFactory(
        $loop, new PhpHandler(), $log, $redis, $asyncRedis, $conf->get('session_length'),
        $db, INSTANCE_ID, $stratBuilder->build($opts['flags'])
    ))->build();

    $wss = new Ratchet\WebSocket\WsServer($pipeManager);
    $wss->disableVersion(0);

    $https   = new HttpServer($wss);
    $webSock = new Server($loop);

    try {
        $webSock->listen($opts['port'], '0.0.0.0');
        $log->info("Listening for incoming websockets on port {$opts['port']}...", [ 'char' => '+' ]);

    } catch (ConnectionException $e) {
        $log->alert('Unable to launch: ' . $e->getMessage(), [ $e ]);
        throw $e;
    }

    new Ratchet\Server\IoServer($https, $webSock, $loop);
});



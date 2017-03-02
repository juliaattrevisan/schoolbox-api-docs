#!/usr/bin/hhvm
<?php

use Alaress\Redis\Async;
use Alaress\WorkerCrew\Pooling\ConfigurationReader;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategyBuilder;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Aura\Di;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use Schoolbox\IConfigLoader;
use Schoolbox\Realtime\Pipe\PipeCollection;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\SessionValidator;
use Schoolbox\Realtime\Subscription;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('vp::c::', $fields);

foreach ($fields as $k => $name) {
    if (!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));


require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Socket', array(
    'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'port'    => empty($opts['p']) ? 9081 : $opts['p'],
    'conf'    => empty($opts['c']) ? 'socketd.conf' : $opts['c'],
    'fields'  => array_values($fields),
    'flags'   => $flags
));

// CREATE WEBSOCKET APP
$kernel->run(function (Di\Container $di, LoopInterface $loop, LoggerInterface $log, array $opts) {

    /** @var Redis|ClientInterface $redis */
    $redis = $di->get('redis');

    // TODO put this in DI
    $asyncRedis = new Async\Client($di->get('config')->get('redis_url'), $loop);

    /** @var Database $db */
    $db = $di->get('database');

    /** @var IConfigLoader $conf */
    $conf = $di->get('config');

    $stratBuilder = new ConfiguredDispatchStrategyBuilder((new ConfigurationReader($opts['conf']))->readConfig());

    $delim = in_array('porcelain', $opts[ 'fields' ]) ? ' ' : "\n    ";

    $log->info(implode($delim, array( "Using strategy:" ) + $stratBuilder->summarise()), [ 'char' => '+' ]);

    /**
     * @param LoopInterface $loop
     * @param LoggerInterface $log
     * @param ClientInterface $redis
     * @param Async\Client $asyncRedis
     * @param int $sessionLength
     * @param Database $db
     * @param string $instanceId
     * @param DispatchStrategyInterface $strat
     * @return PipeManager
     */
    function buildPipeManager($loop, $log, $redis, $asyncRedis, $sessionLength, $db, $instanceId, $strat)
    {
        $validator   = new SessionValidator($redis, new PhpHandler(), $sessionLength, $db);
        $pipes       = new PipeCollection($validator, $log);
        $subFactory  = new Subscription\Redis\SubscriptionFactory($redis, $asyncRedis);
        $subManager  = new Subscription\SubscriptionManager($instanceId, $subFactory, $log);
        $pipeManager = new PipeManager($loop, $pipes, $subManager, $log, $strat);

        // setup the listener
        $subFactory->setListener($pipeManager);

        return $pipeManager;
    }

    $pipeManager = buildPipeManager(
        $loop, $log, $redis, $asyncRedis, $conf->get('session_length'),
        $db, INSTANCE_ID, $stratBuilder->build($opts['flags'])
    );

    $wss = new Ratchet\WebSocket\WsServer($pipeManager);
    $wss->disableVersion(0);

    $https = new Ratchet\Http\HttpServer($wss);

    try {
        $webSock = new React\Socket\Server($loop);
        $webSock->listen($opts['port'], '0.0.0.0');

        new Ratchet\Server\IoServer($https, $webSock, $loop);

        $log->info("Listening for incoming websockets on port {$opts['port']}...", [ 'char' => '+' ]);

    } catch (ConnectionException $e) {
        $log->alert("Unable to launch: " . $e->getMessage(), [ $e ]);
        throw $e;
    }
});



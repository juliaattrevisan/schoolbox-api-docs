#!/usr/bin/hhvm
<?php

use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategy;
use Aura\Di;
use Ratchet\Session\Serialize\PhpHandler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\SessionValidator;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$opts = getopt('vp::c::');

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Socket', array(
	'verbose' => isset($opts['v']),
	'port'    => empty($opts['p']) ? 9081 : $opts['p'],
	'conf'    => empty($opts['c']) ? 'socketd.conf' : $opts['c'],
));

// CREATE WEBSOCKET APP
$kernel->run(function($di, $loop, $debug, $opts) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var Debug $debug
	 * @var array $opts
	 */
	$redis = Alaress\Redis::getClientInstance();
	$redis->connect();

	if(file_exists($opts['conf']) && is_readable($opts['conf'])) {
		$fp = fopen($opts['conf'], 'r');
		while($line = fgets($fp)) {
			$xpl = explode('=',$line);
			if(count($xpl) == 2) {
				${$xpl[0]} = $xpl[1];
			}
		}
		fclose($fp);

		$poolStrat = new ConfiguredDispatchStrategy(
			isset($minWorkers)  ? (int)$minWorkers  : DefaultDispatchStrategy::MIN_WORKERS,
			isset($maxWorkers)  ? (int)$maxWorkers  : DefaultDispatchStrategy::MAX_WORKERS,
			isset($maxFlywheel) ? (int)$maxFlywheel : DefaultDispatchStrategy::MAX_FLYWHEEL,
			isset($queueDelay)  ? (int)$queueDelay  : DefaultDispatchStrategy::QUEUE_DELAY,
			isset($queueWarnSize) ? (int)$queueWarnSize : DefaultDispatchStrategy::QUEUE_SIZE_WARN,
			isset($queueMaxSize)  ? (int)$queueMaxSize  : DefaultDispatchStrategy::QUEUE_SIZE_MAX,
			isset($idleTimeout) ? (int)$idleTimeout : DefaultDispatchStrategy::IDLE_TIMEOUT
		);

		$debug->v(sprintf("Current strat: workers [%d - %d], queueing (%dms <= %d)",
			isset($minWorkers)  ? (int)$minWorkers  : DefaultDispatchStrategy::MIN_WORKERS,
			isset($maxWorkers)  ? (int)$maxWorkers  : DefaultDispatchStrategy::MAX_WORKERS,
			isset($queueDelay)  ? (int)$queueDelay  : DefaultDispatchStrategy::QUEUE_DELAY,
            isset($queueMaxSize)  ? (int)$queueMaxSize  : DefaultDispatchStrategy::QUEUE_SIZE_MAX
		));
	}
	else {
		$poolStrat = new DefaultDispatchStrategy();
		$debug->v("Using default strat");
	}

    $sessionValidator = new SessionValidator($redis, new PhpHandler(), $di->get('config')->get('session_length'));

    $pipeManager = new PipeManager($loop, $sessionValidator, $debug, $poolStrat);

	$wss = new Ratchet\WebSocket\WsServer($pipeManager);
	$wss->disableVersion(0);

	$https = new Ratchet\Http\HttpServer($wss);

	try {
		$webSock = new React\Socket\Server($loop);
		$webSock->listen($opts['port'], '0.0.0.0');

		new Ratchet\Server\IoServer($https, $webSock, $loop);

		$debug->v("Listening for incoming websockets on port {$opts['port']}...", '+');
	}
	catch(ConnectionException $e) {
		$debug->v("Unable to launch: " . $e->getMessage(), '!');
		throw $e;
	}

});



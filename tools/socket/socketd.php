#!/usr/bin/hhvm
#!/usr/bin/php
<?php
use React\Socket\ConnectionException;
use Schoolbox\Realtime\Pipe\PipeManager;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\ConfiguredDispatchStrategy;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

error_reporting(E_ALL ^ E_NOTICE);

require_once "mini-bootstrap.php";

$opts = getopt('vp::c::');

$verbose = isset($opts['v']);
$port = empty($opts['p']) ? 9081 : $opts['p'];
$conf = empty($opts['c']) ? 'socketd.conf' : $opts['c'];

if($verbose) {
	echo <<<EOL

 ||
 || Schoolbox WebSocket Daemon
 ||


EOL;

}

$verboser = new \Schoolbox\Realtime\Verboser($verbose ? fopen("p$i.log", 'w') : null);

$verboser->setPort($i);

pcntl_signal(SIGCHLD, SIG_IGN);

$loop = \React\EventLoop\Factory::create();

// CREATE WEB SOCKET ASYNC
Alaress\Redis::getInstance()->connect();
$redis = Alaress\Redis::getClientInstance();
$redis->connect();

if(file_exists($conf) && is_readable($conf)) {
	$fp = fopen($conf, 'r');
	while($line = fgets($fp)) {
		$xpl = explode('=',$line);
		if(count($xpl) == 2) {
			${$xpl[0]} = $xpl[1];
		}
	}
	fclose($fp);
	$poolstrat = new ConfiguredDispatchStrategy(
		isset($minWorkers)  ? (int)$minWorkers  : DefaultDispatchStrategy::MIN_WORKERS,
		isset($maxWorkers)  ? (int)$maxWorkers  : DefaultDispatchStrategy::MAX_WORKERS,
		isset($maxFlywheel) ? (int)$maxFlywheel : DefaultDispatchStrategy::MAX_FLYWHEEL,
		isset($queueDelay)  ? (int)$queueDelay  : DefaultDispatchStrategy::QUEUE_DELAY,
		isset($idleTimeout) ? (int)$idleTimeout : DefaultDispatchStrategy::IDLE_TIMEOUT
	);

	$verboser->v(sprintf("Current strat: workers [%d - %d], queueing (%dms * %d)",
		isset($minWorkers)  ? (int)$minWorkers  : DefaultDispatchStrategy::MIN_WORKERS,
		isset($maxWorkers)  ? (int)$maxWorkers  : DefaultDispatchStrategy::MAX_WORKERS,
		isset($queueDelay)  ? (int)$queueDelay  : DefaultDispatchStrategy::QUEUE_DELAY,
		isset($maxFlywheel) ? (int)$maxFlywheel : DefaultDispatchStrategy::MAX_FLYWHEEL
	));
}
else {
	$poolstrat = new DefaultDispatchStrategy();
	$verboser->v("Using default strat");
}

$pipeManager = new PipeManager($loop, $redis, $verboser, $poolstrat);

$wss = new Ratchet\WebSocket\WsServer($pipeManager);
$wss->disableVersion(0);

$https = new Ratchet\Http\HttpServer($wss);

try {
	$webSock = new React\Socket\Server($loop);
	$webSock->listen($i, '0.0.0.0');

	$webServer = new Ratchet\Server\IoServer($https, $webSock, $loop);

	$verboser->v("Listening for incoming websockets on port $i...", '+');
	$loop->run();
}
catch(ConnectionException $e) {
	$verboser->v("Unable to launch: " . $e->getMessage(), '!');
}


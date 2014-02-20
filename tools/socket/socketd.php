#!/usr/bin/php
#!/usr/bin/hhvm
<?php
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\UserMapper;

/**
 * The Websocket Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

pcntl_signal(SIGCHLD, SIG_IGN);
error_reporting(E_ALL ^ E_NOTICE);

require_once('../../include/Bootstrap.php');

$opts = getopt('dvp::');

$verbose = isset($opts['v']);
$port = empty($opts['p']) ? 8080 : $opts['p'];
$debug = isset($opts['d']);

if($verbose) {
	echo <<<EOL

 ||
 || Schoolbox WebSocket Daemon
 ||


EOL;

}

$loop = \React\EventLoop\Factory::create();

// CREATE WEB SOCKET ASYNC
$redis = Alaress\Redis::getClientInstance();

$verboser = new \Schoolbox\Realtime\Verboser($verbose ? STDOUT : null);

$pipeManager = new PipeManager(
	$loop, $redis, $verboser
);

$session = new \Ratchet\Session\SessionProvider(
	$pipeManager, new Predis\Session\SessionHandler($redis)
);

$wss = new Ratchet\WebSocket\WsServer($session);
$wss->disableVersion(0);

$https = new Ratchet\Http\HttpServer($wss);

$webSock = new React\Socket\Server($loop);
$webSock->listen($port, '0.0.0.0');

$webServer = new Ratchet\Server\IoServer($https, $webSock, $loop);

$verboser->v("Listening for incoming websockets on port $port...", '+');
$loop->run();
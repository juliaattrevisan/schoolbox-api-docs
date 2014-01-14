#!/usr/bin/php
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

require_once('../../include/Bootstrap.php');

$opts = getopt('vp::');

$verbose = isset($opts['v']);
$port = $opts['p'] ?: 8080;


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
	$loop, $redis, UserMapper::getInstance(), $verboser
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

if($verbose) {
	echo "[ ] Listening for incoming websockets on port $port...\n";
}
$loop->run();
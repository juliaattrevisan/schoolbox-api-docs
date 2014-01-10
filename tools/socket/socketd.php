#!/usr/bin/php
<?php
use Schoolbox\Realtime\PipeManager;
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
$port = isset($opts['p']) ? $opts['p'] : 8080;

// vhost has no power here, manually define the redis sessionhandler
// but I'm not actually sure that this does anything
ini_set('session.gc_maxlife', 43200);
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://127.0.0.1:6379');

$loop = \React\EventLoop\Factory::create();

// CREATE WEB SOCKET ASYNC
$redis = Alaress\Redis::getClientInstance();

$pipeManager = new PipeManager(
	$loop, $redis, UserMapper::getInstance(), $verbose ? STDOUT : null
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
#!/usr/bin/php
<?php
use React\EventLoop\Factory as EventLoopFactory;
use React\Stomp\Factory as StompFactory;
use Schoolbox\Realtime\Pipe\Responder;
use Schoolbox\Realtime\Stomp\SubscriptionManager;
use Schoolbox\Realtime\Verboser;

/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once "mini-bootstrap.php";

// depending on how this process is started, the vars could be in either of these globals
$glob = array_merge($_ENV, $_SERVER);

$command = isset($glob['c']) ? strtolower($glob['c']) : '';
$args    = isset($glob['a']) ? (array)json_decode($glob['a'], true) : array();

if(!in_array($command, array('subscribe','fetch','ack','push'))) {
	echo Responder::CONTEXT_SELF . Responder::CONTEXT_DELIMITER . "Bad command '$command'.";
	exit(1);
}

$responder = new Responder(true);

$method = array($responder, $command);

if(is_callable($method)) {
	if($command == 'fetch') {
		array_push($args, new \Schoolbox\Notif\MQ\Connector());
	}
	call_user_func_array($method, $args);
}
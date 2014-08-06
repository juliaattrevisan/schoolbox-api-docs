#!/usr/bin/php
<?php

use Aura\Di;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Digest\DigestScheduler;

/**
 * The Digest Daemon.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


$opts = getopt('v', \Alaress\Lumberjack\Logger::getAvailableFields());

$fields = array();
foreach(\Alaress\Lumberjack\Logger::getAvailableFields() as $name) {
    if(isset($opts[$name])) {
        $fields[] = $name;
    }
}

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest', array(
	'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => $fields
));



// CREATE DIGEST APP
$kernel->run(function($di, $loop, $log, $opts) {
	/**
	 * @var Di\Container $di
	 * @var LoopInterface $loop
	 * @var LoggerInterface $log
	 * @var array $opts
	 */

	try {
		new DigestScheduler($loop, new DefaultDispatchStrategy(), $log);
	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.");
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}

});

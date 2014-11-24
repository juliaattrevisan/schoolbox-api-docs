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

$fields = array('porcelain', 'mem', 'truemem', 'pid');

$opts = getopt('v', $fields);

foreach($fields as $k => $name) {
    if(!isset($opts[$name])) {
        unset($fields[$k]);
    }
}

$flags = implode(' ', array_slice($argv, 1));

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('\\Schoolbox\\Application\\Daemon');

$kernel = new Schoolbox\Application\Daemon\Kernel('Digest', array(
	'verbose' => isset($opts['v']) ? $opts['v'] : null,
    'fields'  => array_values($fields),
    'flags'   => $flags
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

        // make sure our endpoint exists
        $mqc = \Schoolbox\Notif\MQ\Connector::getInstance();
        $q   = INSTANCE_ID . DigestScheduler::QUEUE_COMMAND;

        $mqc->declareQueue($q)
            ->bindQueue($q, '*.' . Notif::TOPIC_CMD_DIGEST);

        $conf = new \Schoolbox\Config\Loader();

        $stompFactory = new \React\Stomp\Factory($loop);
        $client = $stompFactory->createClient(array(
            'host'      => $conf->get('amqp_host'),
            'port'      => $conf->get('amqp_stomp_port'),
            'vhost'     => $conf->get('amqp_vhost'),
            'login'     => $conf->get('amqp_user'),
            'passcode'  => $conf->get('amqp_pass'),
        ));

        new DigestScheduler($loop, new DefaultDispatchStrategy($opts['flags']), $client, $log, $conf);
	}
	catch(AMQPRuntimeException $e) {
		$log->alert("Unable to connect to RabbitMQ: server is offline.");
		$n = new Notif(Notif::TOPIC_NOTIFICATION);
		$n->alert('RabbitMQ is offline');
		throw $e;
	}

});

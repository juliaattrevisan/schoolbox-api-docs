#!/usr/bin/php
<?php
/**
 * This script should read the schedule queue, and attempt to safely repair any damaged messages in it
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel('Schedule Queue Dump');
pcntl_signal(SIGCHLD, SIG_DFL);
error_reporting(E_ALL);

restore_error_handler();
restore_exception_handler();

$d = new \Alaress\Lumberjack\Logger(STDOUT, STDERR, 100);

$d->info("Starting queue dump");

$loop = \React\EventLoop\Factory::create();

$stompFactory = new \React\Stomp\Factory($loop);

$stomp = $stompFactory->createClient(array(
	'host'      => Cfg::get('amqp_host'),
	'port'      => Cfg::get('amqp_stomp_port'),
	'vhost'     => Cfg::get('amqp_vhost'),
	'login'     => Cfg::get('amqp_user'),
	'passcode'  => Cfg::get('amqp_pass'),
));

$seen = new stdClass();

/**
 * @var $stomp \React\Stomp\Client $stomp
 */
$stomp->connect()->then(function($stomp) use ($d, $seen, $loop) {

	$timer = $loop->addTimer(4, function() { exit(0); });

	/**
	 * @param \React\Stomp\Protocol\Frame $frame
	 * @param \React\Stomp\AckResolver $resolver
	 */
	$stomp->subscribeWithAck(INSTANCE_ID.'.sb.sched', 'client-individual', function($frame, $resolver) use ($d, $seen, $loop, &$timer) {
			$loop->cancelTimer($timer);
			$timer = $loop->addTimer(4, function() { exit(0); });

			$date = date('Y-m-d H:i:s', $frame->getHeader('timestamp'));

			// do this for every message that comes in
			$d->info("Processing message (arrived at $date)", '>');

			Database::reinitialise();
			DeprecatedDatabase::reinitialise();

			try {
				$formatted = \Schoolbox\Notif\Formatter\CompressedFormatter::uncompress($frame->body);
				$event = unserialize($formatted['message']);

				// if the interrogation says to dismiss the message, we do
				interrogate($event, $d);

				// hold onto our NACK's
				return;
			}
			catch(RuntimeException $e) {
				// this message is can't be parsed, so we ACK it to make it go away
				$d->critical("RuntimeException: ".$e->getMessage(), array($e));
				$d->notice("Message unparseable", array('char' => '-'));
			}
			catch(\Schoolbox\Realtime\Schedule\ScheduleException $e) {
				// we're happy to ignore this, it just means no scheduling strategy exists for this Model
				$d->error('Schedule Exception: ' . $e->getMessage(), array($e));
				$d->notice("Message ignorable", array('char' => '-'));
			}
			catch(\Schoolbox\Notif\Formatter\CompressionException $e) {
				// this message is unparseable, so we'll eventually ACK it to make it go away
				$d->critical('Compression Exception: ' . $e->getMessage(), array($e));
				$d->notice("Message unparseable", array('char' => '-'));
			}

		});
});


$loop->run();



/**
 * @param \Schoolbox\Realtime\Schedule\Event $event
 * @param \Psr\Log\LoggerInterface $log
 * @throws \Schoolbox\Realtime\Schedule\ScheduleException
 * @return bool whether or not to acknowledge the message
 */
function interrogate($event, $log) {
	// setup for ifs
	$modelClass = get_class($event->getObject());
	$pregClass = preg_replace('/([_\\\\])[^_\\\\]+$/', '$1Notifier', $modelClass);

	// first try: does the event have a mapper, and if so, does it have a modulenotifier declared?
	if($event->getMapper() instanceof \Schoolbox\Mapper && $event->getMapper()->getModuleNotifier()) {
		$classname = $event->getMapper()->getModuleNotifier();
	}
	// next, try for '\Schoolbox\User' => '\Schoolbox\UserNotifier'
	elseif(class_exists($modelClass.'Notifier')) {
		$classname = $modelClass.'Notifier';
	}
	// then, try for '\Schoolbox\User' => '\Schoolbox\Notifier'
	elseif(class_exists($pregClass)) {
		$classname = $pregClass;
	}
	else {
		throw new \Schoolbox\Realtime\Schedule\ScheduleException("No notifier for model '$modelClass'.");
	}

	$instance = new $classname(new \Schoolbox\ConfigLoader());
	if(!$instance instanceof \Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface) {
		throw new \Schoolbox\Realtime\Schedule\ScheduleException("Notifer class '$classname' must be instance of ModuleNotifierInterface.");
	}

	$object = $event->getObject();

	$mapperClass = get_class($event->getMapper());

	$objectClass = get_class($object);

	$objectId = method_exists($object, 'getId') ? $object->getId() : 'unkn';

	$effectorId = $event->getEffector()->getId();

	$action = $event->getAction();

	$log->notice(json_encode(array(
		'mapper' => $mapperClass,
		'object' => array(
			'class' => $objectClass,
			'id' => $objectId
		),
		'effector' => $effectorId,
		'crud' => strtoupper($action)
	)));

}

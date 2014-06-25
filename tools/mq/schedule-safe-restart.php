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
new \Schoolbox\Application\Daemon\Kernel('Schedule Safe Restart');
pcntl_signal(SIGCHLD, SIG_DFL);
error_reporting(E_ALL);

restore_error_handler();
restore_exception_handler();

$opts = getopt('',array('perform'));
$perform = isset($opts['perform']);

$d = new \Alaress\Cli\Debug(STDOUT);

$d->v("Starting schedule diagnostics...");

if($perform) {
	$d->v("Running with the safety off, you have 3 seconds to change your mind", '!');
	sleep(3);
}
else {
	$d->v("Doing diagnostic only, no data will be harmed (despite what messages say)");
}

$loop = \React\EventLoop\Factory::create();

$stompFactory = new \React\Stomp\Factory($loop);

$stomp = $stompFactory->createClient(array(
	'host'      => Cfg::get('amqp_host'),
	'port'      => Cfg::get('amqp_stomp_port'),
	'vhost'     => '/',
	'login'     => Cfg::get('amqp_user'),
	'passcode'  => Cfg::get('amqp_pass'),
));

$seen = new stdClass();

$amqpConn = new \Schoolbox\Notif\MQ\Connector();
$amqpConn->declareQueue(INSTANCE_ID.'.sb.reviewsched');
$amqpConn->bindQueue(INSTANCE_ID.'.sb.reviewsched', 'deadmessage');
$amqpConn->closeConnection();
unset($amqpConn);

/**
 * @var $stomp \React\Stomp\Client $stomp
 */
$stomp->connect()->then(function($stomp) use ($d, $seen, $loop, $perform) {

	$timer = $loop->addTimer(4, function() { exit(0); });

	/**
	 * @param \React\Stomp\Protocol\Frame $frame
	 * @param \React\Stomp\AckResolver $resolver
	 */
	$stomp->subscribeWithAck(INSTANCE_ID.'.sb.sched', 'client-individual', function($frame, $resolver) use ($d, $seen, $loop, &$timer, $perform) {
			$loop->cancelTimer($timer);
			$timer = $loop->addTimer(4, function() { exit(0); });

			// do this for every message that comes in
			$d->v('Processing message', '>');

			Database::reinitialise();
			DeprecatedDatabase::reinitialise();

			try {
				$formatted = \Schoolbox\Notif\Formatter\CompressedFormatter::uncompress($frame->body);
				$event = unserialize($formatted['message']);

				// if the interrogation says to dismiss the message, we do
				if(interrogate($event, $d)) {
					$d->v("Message broken", '*');

					if($perform) {
						// split this message off onto a special queue
						$amqpConn = new \Schoolbox\Notif\MQ\Connector();
						$amqpConn->publish($frame->body, 'deadmessage');
						$amqpConn->closeConnection();
						unset($amqpConn);
						$resolver->ack();
					}
				}
				else {
					$d->v("Message retained", '+');
				}

				// hold onto our NACK's
				return;
			}
			catch(RuntimeException $e) {
				// this message is can't be parsed, so we ACK it to make it go away
				$d->v("RuntimeException: ".$e->getMessage(), '!');
				$d->v("Message unparseable", '-');
			}
			catch(\Schoolbox\Realtime\Schedule\ScheduleException $e) {
				// we're happy to ignore this, it just means no scheduling strategy exists for this Model
				$d->v('Schedule Exception: ' . $e->getMessage(), '*');
				$d->v("Message ignorable", '-');
			}
			catch(\Schoolbox\Notif\Formatter\CompressionException $e) {
				// this message is unparseable, so we'll eventually ACK it to make it go away
				$d->v('Compression Exception: ' . $e->getMessage(), '!');
				$d->v("Message unparseable", '-');
			}

			if($perform) {
				$resolver->ack();
			}
		});
});


$loop->run();



/**
 * @param \Schoolbox\Realtime\Schedule\Event $event
 * @param \Alaress\Cli\Debug $d
 * @throws \Schoolbox\Realtime\Schedule\ScheduleException
 * @return bool whether or not to acknowledge the message
 */
function interrogate($event, $d) {
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

	$d->v(sprintf("Got a module instance of %s, continuing...", get_class($instance)));

	// BEWARE! CUTLERY!
	$pid = pcntl_fork();

	switch($pid) {
		case -1: return false; // failed to fork
		case 0: // I am child
			break;
		default: // I am parent
			pcntl_waitpid($pid, $status);

			$exitCode = pcntl_wexitstatus($status);
			// if zero exit code, we want to retain this message
			if($exitCode === 0) {
				$d->v("All clear!");
				return false;
			}

			// message is borked
			return true;
	}

	// YOU ARE IN CHILD CONTEXT HERE. DO NOT ATTEMPT TO RETURN. YOU MUST EXIT INSTEAD.
	register_shutdown_function(function($d) {
		$last = error_get_last();

		if(in_array($last['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			$d->v(sprintf("%s in %s:%s", $last['message'], $last['file'], $last['line']), '!');
			exit(1);
		}
	}, $d);


	$rf = new ReflectionObject($instance);
	$methods = $rf->getMethods(ReflectionMethod::IS_PUBLIC);

	foreach($methods as $m) {
		if(!preg_match('`^notify(Staff|Student|Parent)`', $m->getName())) {
			continue;
		}

		$d->v(sprintf("Testing event against %s::%s", get_class($instance), $m->getName()));

		// attempt to invoke with the current arguments. if it dies, the message will be dismissed
		try {
			$m->invoke($instance, $event->getObject(), $event->getEffector(), $event->getDiff());
		}
		catch(Exception $e) {
			$d->v(sprintf("Caught exception - %s: %s", get_class($e), $e->getMessage()), '!');
		}
	}

	exit(0);
}

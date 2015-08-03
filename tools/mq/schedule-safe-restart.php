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

use Psr\Log\LoggerInterface;
use React\Stomp\Protocol\Frame;
use Schoolbox\Realtime\Schedule\Event;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
new \Schoolbox\Application\Daemon\Kernel('Schedule Safe Restart');
pcntl_signal(SIGCHLD, SIG_DFL);
error_reporting(E_ALL);

restore_error_handler();
restore_exception_handler();

$opts = getopt('v',array('perform'));
$perform = isset($opts['perform']);

$threshold = 0;
if(isset($opts['v'])) {
    $threshold = 1;
    if(is_array($opts['v'])) {
        $threshold = min(3, count($opts['v']));
    }
}

$limits = array(
    0 => 500, // nil verbosity: exceptional behaviour only
    1 => 250, // min verbosity: significant events
    2 => 200, // mod verbosity: most things
    3 => 100  // max verbosity: EV-ER-Y-THING...
);

$d = new \Alaress\Lumberjack\Logger(STDOUT, STDERR, $limits[$threshold], array('truemem'));

$d->info("Starting schedule diagnostics...");

if($perform) {
	$d->warning("Running with the safety off, you have 3 seconds to change your mind");
	sleep(3);
}
else {
	$d->notice("Doing diagnostic only, no data will be harmed (despite what messages say)");
}

set_error_handler(
    function ($num, $string, $file, $line) {
        // cast catchable fatal error to runtime exception so it gets ditched
        throw new RuntimeException("$string in $file:$line");
    },
    E_RECOVERABLE_ERROR
);

// we have to fetch all because the DB conn is about to be reset :/
$sqlq = Database::getInstance()->query("SELECT * FROM scheduleq")->fetchAll(PDO::FETCH_ASSOC);

// init this
DeprecatedDatabase::getInstance();

foreach ($sqlq as $qi) {

    // do this for every message that comes in
    $d->info("Processing message from SQL", array('char' => '>'));

    Database::reinitialise();
    DeprecatedDatabase::reinitialise();

    try {
        $event = unserialize($qi['identifier']);

        // if the interrogation says to dismiss the message, we do
        if (interrogate($event, $d)) {
            $d->error("Message broken");

            if ($perform) {
                // split this message off onto a special queue
		        Database::reinitialise();
                Database::getInstance()->exec("DELETE FROM scheduleq WHERE id_hash = '".$qi['id_hash']."'");
            }
        }
        else {
            $d->notice("Message retained", array('char' => '+'));
        }

    } catch (RuntimeException $e) {
        // this message is can't be parsed, so we ACK it to make it go away
        $d->error("RuntimeException: ".$e->getMessage());
        $d->notice("Message unparseable", array('char' => '-'));

    } catch (\Schoolbox\Realtime\Schedule\ScheduleException $e) {
        // we're happy to ignore this, it just means no scheduling strategy exists for this Model
        $d->warning('Schedule Exception: ' . $e->getMessage());
        $d->notice("Message ignorable", array('char' => '-'));

    } catch (\Schoolbox\Notif\Formatter\CompressionException $e) {
        // this message is unparseable, so we'll eventually ACK it to make it go away
        $d->error('Compression Exception: ' . $e->getMessage());
        $d->notice("Message unparseable", array('char' => '-'));

    }

    if ($perform) {
        Database::reinitialise();
        Database::getInstance()->exec("DELETE FROM scheduleq WHERE id_hash = '".$qi['id_hash']."'");
    }

}

// now move onto stuff in rabbit
$loop = \React\EventLoop\Factory::create();

$stompFactory = new \React\Stomp\Factory($loop);

$stomp = $stompFactory->createClient(array(
	'host'      => Cfg::get('amqp_host'),
	'port'      => Cfg::get('amqp_stomp_port'),
	'vhost'     => Cfg::get('amqp_vhost'),
	'login'     => Cfg::get('amqp_user'),
	'passcode'  => Cfg::get('amqp_pass'),
));

$amqpConn = \Schoolbox\Notif\MQ\Connector::getInstance();
$amqpConn->declareQueue(INSTANCE_ID.'.sb.reviewsched');
$amqpConn->bindQueue(INSTANCE_ID.'.sb.reviewsched', 'deadmessage');
$amqpConn->closeConnection();
unset($amqpConn);

/**
 * @var $stomp \React\Stomp\Client $stomp
 */
$stomp->connect()->then(function($stomp) use ($d, $loop, $perform) {

	$timer = $loop->addTimer(4, function() { exit(0); });

    /**
     * @var $stomp \React\Stomp\Client $stomp
     */
    $stomp->subscribeWithAck(INSTANCE_ID.'.sb.sched', 'client-individual', function($frame, $resolver) use ($d, $loop, &$timer, $perform) {
            /**
             * @var \React\Stomp\Protocol\Frame $frame
             * @var \React\Stomp\AckResolver $resolver
             */
			$loop->cancelTimer($timer);
			$timer = $loop->addTimer(4, function() { exit(0); });

			$date = date('Y-m-d H:i:s', $frame->getHeader('timestamp'));

			// do this for every message that comes in
			$d->info("Processing message (arrived at $date)", array('char' => '>'));

			Database::reinitialise();
			DeprecatedDatabase::reinitialise();

			// if the interrogation says to dismiss the message, we do
			if(interrogate($frame, $d)) {
				$d->error("Message broken");

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
				$d->notice("Message retained", array('char' => '+'));
			}

		});
});


$loop->run();



/**
 * @param LoggerInterface $log
 * @param Frame           $frame
 * @return bool whether or not to acknowledge the message
 */
function interrogate(Frame $frame, LoggerInterface $log)
{
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
				$log->notice("All clear!");
				return false;
			}

			// message is borked
			return true;
	}

	// YOU ARE IN CHILD CONTEXT HERE. DO NOT ATTEMPT TO RETURN. YOU MUST EXIT INSTEAD.
	restore_error_handler();
	register_shutdown_function(function($d) {
		/** @var \Psr\Log\LoggerInterface $d */
		$last = error_get_last();

		if(in_array($last['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
			$d->error(sprintf("%s in %s:%s", $last['message'], $last['file'], $last['line']));
			exit(1);
		}
	}, $log);

	$formatted = \Schoolbox\Notif\Formatter\CompressedFormatter::uncompress($frame->body);
	$event = unserialize($formatted['message']);

	if (!$event instanceof Event) {
		$log->critical("Could not unserialize message data");
		exit(2);
	}

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
		$log->notice("No notifier for model '$modelClass'.");
		exit(2);
	}

	$instance = new $classname(new \Schoolbox\ConfigLoader());
	if(!$instance instanceof \Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface) {
		$log->error("Notifer class '$classname' must be instance of ModuleNotifierInterface.");
		exit(2);
	}

	$log->debug(sprintf("Got a module instance of %s, continuing...", get_class($instance)));

	$exit = 0;

	// test trigger bundling
	try {
		$instance->bundleTriggers(new \Schoolbox\Realtime\Schedule\Trigger\Test\StubTriggerBundle($event, \Schoolbox\Mapper::METHOD_CREATE));
		$instance->bundleTriggers(new \Schoolbox\Realtime\Schedule\Trigger\Test\StubTriggerBundle($event, \Schoolbox\Mapper::METHOD_UPDATE));
		$instance->bundleTriggers(new \Schoolbox\Realtime\Schedule\Trigger\Test\StubTriggerBundle($event, \Schoolbox\Mapper::METHOD_DELETE));
	}
	catch(Exception $e) {
		$log->error(sprintf("Encountered exception - %s: %s", get_class($e), $e->getMessage()));
		$exit = 1;
	}


	$rf = new ReflectionObject($instance);
	$methods = $rf->getMethods(ReflectionMethod::IS_PUBLIC);

	foreach($methods as $m) {
		if(!preg_match('`^notify(Staff|Student|Parent)`', $m->getName())) {
			continue;
		}

		$log->info(sprintf("Testing event against %s::%s", get_class($instance), $m->getName()));

		// attempt to invoke with the current arguments. if it dies, the message will be dismissed
		try {
			$m->invoke($instance, $event->getObject(), $event->getEffector(), $event->getDiff());
		}
		catch(Exception $e) {
			$log->error(sprintf("Encountered exception - %s: %s", get_class($e), $e->getMessage()));
			$exit = 1;
		}
	}

	exit($exit);
}

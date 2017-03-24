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

use Alaress\Redis;
use Alaress\Scheduler\Trigger\TriggerInterface;
use Aura\Di\ContainerInterface;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\Stomp;
use React\Stomp\Protocol\Frame;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Formatter\CompressedFormatter;
use Schoolbox\Notif\Formatter\CompressionException;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\Propagator;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactoryFactory;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\Daemon');
$kernel = new \Schoolbox\Application\Daemon\Kernel('Schedule Safe Restart');
pcntl_signal(SIGCHLD, SIG_DFL);
error_reporting(E_ALL);

restore_error_handler();
restore_exception_handler();

$opts      = getopt('v', [ 'perform' ], $_);
$perform   = isset($opts['perform']);
$threshold = 0;

if (isset($opts['v'])) {
    $threshold = 1;
    if (is_array($opts['v'])) {
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

if ($perform) {
    $d->warning("Running with the safety off, you have 3 seconds to change your mind");
    sleep(3);
} else {
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
        if (interrogate($event, $d, $kernel->getDependencyInjector())) {
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

    } catch (ScheduleException $e) {
        // we're happy to ignore this, it just means no scheduling strategy exists for this Model
        $d->warning('Schedule Exception: ' . $e->getMessage());
        $d->notice("Message ignorable", array('char' => '-'));

    } catch (CompressionException $e) {
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
$stomp->connect()->then(function (Stomp\Client $stomp) use ($d, $loop, $perform, $kernel) {

    $di = $kernel->getDependencyInjector();

    $timer = $loop->addTimer(4, function() { exit(0); });

    $stomp->subscribeWithAck(INSTANCE_ID.'.sb.sched', 'client-individual', function (Frame $frame, Stomp\AckResolver $resolver) use ($d, $loop, &$timer, $perform, $di) {
        $loop->cancelTimer($timer);
        $timer = $loop->addTimer(4, function() { exit(0); });

        $date = date('Y-m-d H:i:s', $frame->getHeader('timestamp'));

        // do this for every message that comes in
        $d->info("Processing message (arrived at $date)", array('char' => '>'));

        Database::reinitialise();
        DeprecatedDatabase::reinitialise();

        // if the interrogation says to dismiss the message, we do
        if (interrogate($frame, $d, $di)) {
            $d->error("Message broken");

            if ($perform) {
                // split this message off onto a special queue
                $amqpConn = new \Schoolbox\Notif\MQ\Connector();
                $amqpConn->publish($frame->body, 'deadmessage');
                $amqpConn->closeConnection();
                unset($amqpConn);
                $resolver->ack();
            }

        } else {
            $d->notice("Message retained", array('char' => '+'));
        }

    });
});


$loop->run();


/**
 * @param object $object
 * @return Event
 * @throws CompressionException
 * @throws RuntimeException
 */
function extractEvent($object)
{
    // already what we want
    if ($object instanceof Event) {
        return $object;
    }

    // extract event from frame
    if ($object instanceof Frame) {
        if (preg_match('`:[0-9a-f]{40}$`', $object->body)) {
            throw new CompressionException("Payload is a redis key, {$object->body}, go look there");
        }

        $formatted = CompressedFormatter::uncompress($object->body);
        $event     = unserialize($formatted['message']);

        if ($event instanceof Event) {
            return $event;
        }

        throw new RuntimeException("Could not unserialize message data");
    }

    throw new RuntimeException("Cannot extract event data from given object");
}

/**
 * @param Frame           $input
 * @param LoggerInterface $log
 * @return int
 */
function verifyFrameTrigger(Frame $input, LoggerInterface $log)
{
    $exit = 0;

    try {
        $trigger = frame2trigger(Redis::getClientInstance(), $input);
        $log->info(sprintf("Trigger '%s' will fire at %s; testing callbacks", $trigger->getIdString(), (new Alaress_Date_Format($trigger->getTimestamp(), 'U'))->getDatabase()));

        foreach ($trigger->getCallbacks() as $callback) {
            if (is_array($callback) && count($callback) == 2) {

                list ($object, $method) = array_values($callback);

                $methodName = get_class($object) . "::$method";

                if (!$object instanceof ModuleNotifierInterface) {
                    $log->warning("Cannot test $methodName, not an instanceof ModuleNotifierInterface");
                    continue;
                }

                $object->enableLogging($log, false);

                $log->debug("Testing $methodName");

                $e = $trigger->getEvent();

                try {
                    // we're not interested in the return value, we just want to know it can succeed
                    call_user_func_array($callback, [ $e->getObject(), $e->getEffector(), $e->getDiff() ]);
                } catch (\Exception $e) {
                    $log->warning("$methodName failed execution");
                    $exit = 1;
                }

            } else {
                $log->warning("Could not test callback, is not an array");
            }
        }

    } catch (InvalidArgumentException $e) {
        $log->critical('Payload is not a valid trigger instance: '.$e->getMessage());
        $exit = 2;

    } catch (ErrorException $e) {
        $log->critical('Failed to unserialize payload', [ $e ]);
        $exit = 2;
    }

    return $exit;
}


/**
 * @param object             $input either an Event or a Frame containing one
 * @param LoggerInterface    $log   the logger to output to
 * @param ContainerInterface $di
 * @return bool whether or not to acknowledge the message
 */
function interrogate($input, LoggerInterface $log, ContainerInterface $di)
{
    // BEWARE! CUTLERY!
    $pid = pcntl_fork();

    switch ($pid) {
        case -1: return false; // failed to fork
        case 0: // I am child
            break;
        default: // I am parent
            pcntl_waitpid($pid, $status);

            $exitCode = pcntl_wexitstatus($status);
            // if zero exit code, we want to retain this message
            if ($exitCode === 0) {
                $log->notice("All clear!");
                return false;
            }

            // message is borked
            return true;
    }

    // YOU ARE IN CHILD CONTEXT HERE. DO NOT ATTEMPT TO RETURN. YOU MUST EXIT INSTEAD.
    $exit = 0;

    restore_error_handler();
    register_shutdown_function(function (LoggerInterface $d) {
        $last = error_get_last();

        if (in_array($last['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
            $d->error(sprintf("%s in %s:%s", $last['message'], $last['file'], $last['line']));
            exit(1);
        }
    }, $log);

    try {
        $event = extractEvent($input);

        /** @var IConfigLoader $conf */
        $conf  = $di->get('config');

        /** @var Redis|ClientInterface $redis */
        $redis = $di->get('redis');

        $amqp  = Connector::getInstance();

        $splitter = new Splitter($conf, $di, new TriggerFactoryFactory(true));

        // handle this message by splitting this event and propagating its triggers
        (new Propagator($splitter, $redis, $amqp, INSTANCE_ID))->propagateEvent($event);

    } catch (CompressionException $e) {
        $log->debug($e->getMessage());

        // if we failed to inflate, we expect this to be a trigger
        if ($input instanceof Frame) {
            $exit = verifyFrameTrigger($input, $log);
        } else {
            $log->critical($e->getMessage(), [ $e ]);
            $exit = 2;
        }

    } catch (ScheduleException $e) {
        $log->notice($e->getMessage(), [ $e ]);
        $exit = 2;

    } catch (Exception $e) {
        $log->critical($e->getMessage(), [ $e ]);
        $exit = 2;
    }

    exit($exit);
}


/**
 * @param \Predis\ClientInterface     $redis
 * @param \React\Stomp\Protocol\Frame $frame
 * @throws InvalidArgumentException
 * @throws ErrorException
 * @return TriggerInterface
 */
function frame2trigger(\Predis\ClientInterface $redis, \React\Stomp\Protocol\Frame $frame)
{
    $content = $redis->get($frame->body);

    if ($content === '') {
        throw new InvalidArgumentException("Key `{$frame->body}` is not set");
    }

    // try to unserialize: depending on kernel, will either throw EE or just emit warning
    $trigger = unserialize($content);

    // verify it worked
    if ($trigger instanceof TriggerInterface) {
        return $trigger;
    }

    throw new InvalidArgumentException('Could not unserialize trigger');
}


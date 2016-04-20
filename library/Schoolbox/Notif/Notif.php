<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Alaress\Lumberjack;
use ConfigFallback;
use Database;
use Mailer;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use PDOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Realtime\Schedule\Persistence\QueueItem;
use Schoolbox\Realtime\Schedule\Persistence\QueueItemMapper;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 * @package Schoolbox\Notif
 */
class Notif implements NotifInterface {

	/** Note - If you create any extra topics, ensure they are added to the following for auto create:
	 * tools/create-data-dirs.sh
	 */
	const TOPIC_SYSTEM       = 'sys';     // notifications about top-level events
	const TOPIC_NOTIFICATION = 'notif';   //     "           "   the notification system
	const TOPIC_DATABASE     = 'db';      //     "           "   internal database events
	const TOPIC_APPLICATION  = 'sb';      //     "           "   Schoolbox application events (ie. Mappers)
	const TOPIC_SYNERGY      = 'syn';     //     "           "   synergy database events
	const TOPIC_ADMIN        = 'admin';   //     "           "   superuser events (includes assumption)
	const TOPIC_AUTH         = 'login';   //     "           "   authentication events
	const TOPIC_CMS          = 'cms';     //     "           "   cms events to be logged

	const TOPIC_CMD_REROUTE  = 'cmd.reroute'; // "           "   async actions the scheduler needs to perform
	const TOPIC_CMD_DIGEST   = 'cmd.digest';  // "           "   async actions the digester needs to perform

	/** @var string */
	protected $topic;

    /** @var HandlerFactory */
    protected $factory;

    /**
     * Notif constructor.
     *
     * @param string $topic
     * @param IConfigLoader $configLoader
     */
    public function __construct($topic = self::TOPIC_SYSTEM, IConfigLoader $configLoader = null)
    {
        $this->topic = $topic;

        if ($configLoader) {
            // use the provided config
           $conf = $configLoader;

        } elseif (extension_loaded('pdo')) {
            try {
                // try for a config mapper instance
                Database::reinitialise();

                $conf = ConfigMapper::getInstance();
                $conf->get('mail_support_address');

            } catch (PDOException $e) {
                // if something PDO-esque broke, fall back
                $conf = new ConfigFallback();
            }

        } else {
            // fallback to hardcoded config vars
            $conf = new ConfigFallback();
        }

        try {
            // if the rabbit cannot be contacted, this will throw an exception
            $mqc = MQConnector::getInstance();

        } catch (AMQPRuntimeException $e) {
            // TODO this is problematic
            $mqc = null;
        }

        $swift = Mailer::getConfiguredSwiftTransport($conf, true);
        $log   = new Lumberjack\Logger(null);

        $this->factory = new HandlerFactory($conf, $swift, $log, $mqc);
    }


    /**
     * Passthrough to setting the logger on the factory
     *
     * @param LoggerInterface $log
     * @return static
     */
    public function setLogger(LoggerInterface $log)
    {
        $this->factory->setLogger($log);
        return $this;
    }


	/**
     * Return the topic/channel this Notif is currently broadcasting to
     *
	 * @return string
	 */
	public function getTopic()
    {
		return $this->topic;
	}


	/**
     * Set the topic/channel this Notif will broadcast to
     *
	 * @param string $topic
	 * @return self
	 */
	public function setTopic($topic)
    {
		$this->topic = $topic;
		return $this;
	}


	/**
	 * Return an array of handlers based on the current configuration of self, created in ascending order of priority.
	 * Spawns handlers on demand.
	 *
	 * @return HandlerInterface[]
	 */
    protected function getHandlers()
    {
        $stack = [
            HandlerFactory::SYS_FATAL_MAIL,
            HandlerFactory::SYS_FATAL_LOG,
            HandlerFactory::SYS_MAIL,
            HandlerFactory::SYS_LOG,
            HandlerFactory::SYS_EXCEPTION_MAIL
        ];

        if ($this->isSystemTopic($this->topic)) {
            if (in_array($this->topic, [ self::TOPIC_APPLICATION, self::TOPIC_CMD_REROUTE, self::TOPIC_CMD_DIGEST ])) {
                $stack[] = HandlerFactory::SYS_AMQP;
            }

            if (in_array($this->topic, [ self::TOPIC_AUTH, self::TOPIC_CMS ])) {
                // this may well come back to bite me
                $stack[] = $this->topic;
            }

            // resolve the stack so far
            $handlers = $this->factory->getStack($stack);

            // append a file logger
            array_push($handlers, $this->factory->getFileLogHandler($this->topic));

        } else {
            $stack[] = HandlerFactory::USER_INSTANT;
            $stack[] = HandlerFactory::USER_PUSH;
            $stack[] = HandlerFactory::USER_MAIL;

            // resolve the stack so far
            $handlers = $this->factory->getStack($stack);
        }

        // finally append debug handlers
        return array_merge($handlers, $this->factory->getStack([
            HandlerFactory::SYS_NULL_DEBUG,
            HandlerFactory::SYS_DEBUG_MAIL
        ]));

	}


	/**
	 * @param string $topic
	 * @return bool
	 */
	public function isSystemTopic($topic) {
		if(!isset($this->topic_cache)) {
			$this->topic_cache = array();
			// use a little reflection to get the available constants
			$rf = new \ReflectionClass($this);
			foreach($rf->getConstants() as $name => $value) {
				$this->topic_cache[$name] = $value;
			}
		}
		return in_array($topic, $this->topic_cache);
	}


	/**
	 * Logs the given message to the specified channel.
	 * Convenience method equivalent to setChannel()->log();
	 *
	 * @param string $topic
	 * @param int $level
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public function logTo($topic, $level, $message, array $context = array()) {
		$this->setTopic($topic);
		return $this->log($level, $message, $context);
	}


	/**
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return bool
	 */
	public function log($level, $message, array $context = array()) {
		try {
			$log = new Logger($this->topic, $this->getHandlers());
			return $log->addRecord($level, $message, $context);
		}
		catch(\Swift_TransportException $e) {
			// rabbit failed... email failed... at least it's logged to file
			return false;
		}
		catch(AMQPRuntimeException $e) {
			// a rabbit push was attempted, but connection refused, so cache it in SQL instead
			$event = unserialize($message);

			$qi = new QueueItem();
			$qi->setIdentifier($event);
			$qi->setCommand($this->topic);
			$qi->setData(null);
			QueueItemMapper::getInstance()->save($qi);

			return false;
		}
	}

	/**
	 * Detailed debug information.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function debug($message, array $context = array()) {
		return $this->log(Logger::DEBUG, $message, $context);
	}

	/**
	 * Interesting events. Examples: User logs in, SQL logs.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function info($message, array $context = array()) {
		return $this->log(Logger::INFO, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function notice($message, array $context = array()) {
		return $this->log(Logger::NOTICE, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 * Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function warning($message, array $context = array()) {
		return $this->log(Logger::WARNING, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically be logged and monitored.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function error($message, array $context = array()) {
		return $this->log(Logger::ERROR, $message, $context);
	}

	/**
	 * Critical conditions.
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function critical($message, array $context = array()) {
		return $this->log(Logger::CRITICAL, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function alert($message, array $context = array()) {
		return $this->log(Logger::ALERT, $message, $context);
	}

	/**
	 * Emergency: system is unusable.
	 *
	 * @param $message
	 * @param array $context
	 * @return bool
	 */
	public function emergency($message, array $context = array()) {
		return $this->log(Logger::EMERGENCY, $message, $context);
	}

}

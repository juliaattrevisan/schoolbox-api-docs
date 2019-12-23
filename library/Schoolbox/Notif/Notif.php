<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;


use InvalidArgumentException;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Swift_TransportException;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 *
 * @package Schoolbox\Notif
 */
class Notif implements NotifInterface
{

    /** @var string */
    protected $topic;

    /** @var HandlerFactory */
    protected $factory;

    /** @var array */
    protected $topic_cache;

    /**
     * Notif constructor.
     *
     * @param string         $topic
     * @param HandlerFactory $factory
     */
    public function __construct($topic = self::TOPIC_SYSTEM, HandlerFactory $factory)
    {
        $this->topic   = $topic;
        $this->factory = $factory;
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
     * @throws InvalidArgumentException
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
            if (in_array($this->topic, [ self::TOPIC_AUTH, self::TOPIC_CMS ], true)) {
                // this may well come back to bite me
                $stack[] = $this->topic;
            }

            // resolve the stack so far
            $handlers = $this->factory->getStack($stack);

            // append a file logger
            $handlers[] = $this->factory->getFileLogHandler($this->topic);

        } else {
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
    public function isSystemTopic($topic)
    {
        if (!isset($this->topic_cache)) {
            $this->topic_cache = array();
            // use a little reflection to get the available constants
            $rf = new \ReflectionClass($this);
            foreach ($rf->getConstants() as $name => $value) {
                $this->topic_cache[$name] = $value;
            }
        }
        return in_array($topic, $this->topic_cache, true);
    }


    /**
     * Logs the given message to the specified channel.
     * Convenience method equivalent to setChannel()->log();
     *
     * @param string $topic
     * @param int    $level
     * @param string $message
     * @param array  $context
     * @return bool
     */
    public function logTo($topic, $level, $message, array $context = array())
    {
        $this->setTopic($topic);
        return $this->log($level, $message, $context);
    }


    /**
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return bool
     */
    public function log($level, $message, array $context = array())
    {
        try {
            $log = new Logger($this->topic, $this->getHandlers());
            return $log->addRecord($level, $message, $context);

        } catch (Swift_TransportException $e) {
            // rabbit failed... email failed... at least it's logged to file
            return false;

        } catch (InvalidArgumentException $e) {
            // failed to load the handler stack
            return false;
        }
    }


    /**
     * Detailed debug information.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function debug($message, array $context = array())
    {
        return $this->log(Logger::DEBUG, $message, $context);
    }


    /**
     * Interesting events. Examples: User logs in, SQL logs.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function info($message, array $context = array())
    {
        return $this->log(Logger::INFO, $message, $context);
    }


    /**
     * Normal but significant events.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function notice($message, array $context = array())
    {
        return $this->log(Logger::NOTICE, $message, $context);
    }


    /**
     * Exceptional occurrences that are not errors.
     * Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function warning($message, array $context = array())
    {
        return $this->log(Logger::WARNING, $message, $context);
    }


    /**
     * Runtime errors that do not require immediate action but should typically be logged and monitored.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function error($message, array $context = array())
    {
        return $this->log(Logger::ERROR, $message, $context);
    }


    /**
     * Critical conditions.
     * Example: Application component unavailable, unexpected exception.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function critical($message, array $context = array())
    {
        return $this->log(Logger::CRITICAL, $message, $context);
    }


    /**
     * Action must be taken immediately.
     * Example: Entire website down, database unavailable, etc. This should trigger the SMS alerts and wake you up.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function alert($message, array $context = array())
    {
        return $this->log(Logger::ALERT, $message, $context);
    }


    /**
     * Emergency: system is unusable.
     *
     * @param       $message
     * @param array $context
     * @return bool
     */
    public function emergency($message, array $context = array())
    {
        return $this->log(Logger::EMERGENCY, $message, $context);
    }

}

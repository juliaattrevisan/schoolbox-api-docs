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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * This class is intentionally not a Singleton. It should be instantiated once only, yes, but then DI'd around.
 *
 * Class Notif
 *
 * @package Schoolbox\Notif
 */
class Notif implements NotifInterface
{
    /**
     * The various system-level topics in Schoolbox: all of these should have a
     * file logger.
     *
     * Note that `TOPIC_AUTH` and `TOPIC_CMS` do _not_ log to file.
     */
    public const SYSTEM_TOPICS = [
        self::TOPIC_SYSTEM,
        self::TOPIC_NOTIFICATION,
        self::TOPIC_DATABASE,
        self::TOPIC_APPLICATION,
        self::TOPIC_SYNERGY,
        self::TOPIC_ADMIN,
    ];

    /** @var string */
    protected $topic;

    /** @var HandlerFactory */
    protected $factory;

    /**
     * Notif constructor.
     *
     * @param HandlerFactory $factory
     * @param string         $topic
     */
    public function __construct(HandlerFactory $factory, string $topic = self::TOPIC_SYSTEM)
    {
        $this->factory = $factory;
        $this->topic   = $topic;
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

        // Resolve the base stack
        $handlers = $this->factory->getStack($stack);

        // System topics: add a file log handler
        if ($this->isSystemTopic($this->topic)) {
            $handlers[] = $this->factory->getFileLogHandler($this->topic);
        }

        // Add this topic's handler.
        // This could easily be a null handler: that's fine. It could also be
        // an `InvalidArgumentException`.
        $handlers[] = $this->factory->getHandler($this->topic);

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
        return in_array($topic, self::SYSTEM_TOPICS, true);
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
        } catch (TransportExceptionInterface $e) {
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
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
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function emergency($message, array $context = array())
    {
        return $this->log(Logger::EMERGENCY, $message, $context);
    }
}

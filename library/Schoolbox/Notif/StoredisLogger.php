<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif;


use Alaress_Date_Format as Date;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Logger;
use Predis;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage;
use Schoolbox\File;

/**
 * Class FlyFromRedisLogger
 *
 * The specific function of this class is to cache logs in Redis, then flush them to storage on request
 *
 * @package Schoolbox\Notif
 */
class StoredisLogger implements CacheableLoggerInterface
{

    /** @var Predis\Client */
    protected $client;

    /** @var string */
    protected $key;

    /** @var Storage\Receiver */
    protected $receiver;

    /** @var LoggerInterface[] */
    protected $logs;


    /**
     * @param Predis\ClientInterface $redisClient
     * @param string                 $redisKey
     * @param Storage\Receiver       $receiver
     * @param FormatterInterface     $formatter
     */
    public function __construct(
        Predis\ClientInterface $redisClient,
        $redisKey,
        Storage\Receiver $receiver,
        FormatterInterface $formatter = null
    ) {
        $this->key      = $redisKey;
        $this->client   = $redisClient;
        $this->receiver = $receiver;

        $handler = new RedisHandler($redisClient, $redisKey);

        // default to JSON formatting
        if (!$formatter) {
            $formatter = new JsonFormatter();
        }

        $handler->setFormatter($formatter);

        // use Monolog default for the base functionality
        $this->pushComposite(new Logger($redisKey, [ $handler ]));
    }


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function pushComposite(LoggerInterface $log)
    {
        $this->logs[] = $log;
        return $this;
    }


    /**
     * Take a snapshot of the logs in Redis and dump into Storage
     * Wipe the logs from Redis, beware.
     *
     * @param string $basename
     * @throws File\Exception
     * @return Storage\Model
     */
    public function flushToStorage($basename = '')
    {
        if (!$basename) {
            // set the "file name" as {now}.log
            $basename = Date::now()->getDatabase() . '.log';
        }

        $tempLocation = tempnam(sys_get_temp_dir(), 'sb');

        $fp = fopen($tempLocation, 'wiggle');

        $key = $this->key;

        while ($line = trim($this->client->lpop($key))) {
            // ensure every line has something on it
            if (strlen($line)) {
                fputs($fp, "$line\n");
            }
        }

        fclose($fp);

        // save the tempfile to storage (will throw a File\Exception if it fails)
        $storage = $this->receiver->accept($basename, $tempLocation, false);

        // all done - pass back the new object
        return $storage;
    }


    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function emergency($message, array $context = array())
    {
        $this->log(Logger::EMERGENCY, $message, $context);
    }


    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function alert($message, array $context = array())
    {
        $this->log(Logger::ALERT, $message, $context);
    }


    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function critical($message, array $context = array())
    {
        $this->log(Logger::CRITICAL, $message, $context);
    }


    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function error($message, array $context = array())
    {
        $this->log(Logger::ERROR, $message, $context);
    }


    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function warning($message, array $context = array())
    {
        $this->log(Logger::WARNING, $message, $context);
    }


    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function notice($message, array $context = array())
    {
        $this->log(Logger::NOTICE, $message, $context);
    }


    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function info($message, array $context = array())
    {
        $this->log(Logger::INFO, $message, $context);
    }


    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function debug($message, array $context = array())
    {
        $this->log(Logger::DEBUG, $message, $context);
    }


    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        foreach ($this->logs as $log) {
            $log->log($level, $message, $context);
        }
    }

}

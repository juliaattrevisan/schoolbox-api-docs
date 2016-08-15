<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Notif;

use Alaress_Date_Format as Date;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage;
use Schoolbox\File;

/**
 * Logs to a file, then flushes to storage on command.
 *
 * @package Schoolbox\Notif
 */
class StorefileLogger implements CacheableLoggerInterface
{
    /**
     * The file to which logging is done.
     *
     * @var string
     */
    protected $file;

    /** @var resource */
    protected $stream;

    /** @var Predis\Client */
    protected $client;

    /** @var string */
    protected $key;

    /** @var Storage\Receiver */
    protected $receiver;

    /** @var LoggerInterface[] */
    protected $logs;


    /**
     * @param Storage\Receiver       $receiver
     * @param FormatterInterface     $formatter
     */
    public function __construct(
        Storage\Receiver $receiver,
        FormatterInterface $formatter = null
    ) {
        $file = tempnam(sys_get_temp_dir(), '');
        if (!$file) {
            throw new \RuntimeException('Temp file could not be created!');
        }

        $this->receiver = $receiver;

        $handler = new StreamHandler($file);

        // default to JSON formatting
        if (!$formatter) {
            $formatter = new JsonFormatter();
        }

        $handler = $handler->setFormatter($formatter);

        // use Monolog default for the base functionality
        $this->pushComposite(new Logger('storefile', [$handler]));
    }

    /**
     * Clean up after myself when I'm done.
     */
    public function __destruct()
    {
        unlink($this->file);
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

        // save the tempfile to storage (will throw a File\Exception if it fails)
        $storage = $this->receiver->acceptStream($basename, $this->file, false);

        // Remove the stream
        unlink($this->file);

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

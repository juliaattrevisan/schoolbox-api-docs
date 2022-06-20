<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;

use Alaress\Lumberjack\CompositeLoggerInterface;
use Alaress_Date_Format as Date;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Resource\Storage;
use Schoolbox\File;

/**
 * Logs to a file, then flushes to storage on command.
 *
 * @package Schoolbox\Notif
 */
class StorefileLogger implements CacheableLoggerInterface, CompositeLoggerInterface
{
    /**
     * The name of the file logger which this object contains.
     */
    private const LOGGER_NAME = 'storefile';

    /**
     * The file to which logging is done.
     *
     * @var string
     */
    protected $file;

    /** @var resource */
    protected $stream;

    /** @var Storage\IReceiver */
    protected $receiver;

    /** @var LoggerInterface[] */
    protected $logs = [];

    /**
     * How are log messages formatted?
     */
    private FormatterInterface $formatter;

    /**
     * @param string                  $filename
     * @param Storage\IReceiver       $receiver
     * @param FormatterInterface|null $formatter
     */
    public function __construct(
        $filename,
        Storage\IReceiver $receiver,
        FormatterInterface $formatter = null
    ) {
        $this->file = $filename;

        $this->receiver = $receiver;

        // default to JSON formatting
        $this->formatter = $formatter ?? new JsonFormatter();

        // Create the stream handler
        // (the file must exist at construct time at least)
        $this->recreateStreamHandler(false);
    }

    /**
     * Clean up after myself when I'm done.
     */
    public function __destruct()
    {
        // The file might not exist: if it doesn't that's okay
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }


    /**
     * Clears all existing loggers, and adds the logger this stream handler
     * will log to.
     *
     * The stream handler will be re-created with an empty file after calling
     * `flushToStorage`, because the receiver deletes the temp file after moving
     * it to storage.
     *
     * @param bool $createFileIfNotExists
     * @throws RuntimeException if `$this->file` doesn't exist, and
     *                          `$createFileIfNotExists` is false
     */
    private function recreateStreamHandler(bool $createFileIfNotExists): void
    {
        // Clear existing storefile logger
        $tempLogs = [];
        foreach ($this->logs as $log) {
            if ($log instanceof Logger && $log->getName() === self::LOGGER_NAME) {
                unset($log);
            } else {
                $tempLogs[] = $log;
            }
        }
        $this->logs = $tempLogs;

        // Has the file gone missing?
        if (!$this->file) {
            if ($createFileIfNotExists) {
                file_put_contents($this->file, '');
            } else {
                throw new RuntimeException('Temp file does not exist!');
            }
        }

        $handler = new StreamHandler($this->file, Logger::INFO);
        $handler->setFormatter($this->formatter);

        // use Monolog default logger for the base functionality
        $this->pushComposite(new Logger(self::LOGGER_NAME, [$handler]));
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
        $storage = $this->receiver->accept($basename, $this->file, false);

        // Recreate the stream handler,
        // because $this->receiver->accept deleted its file
        $this->recreateStreamHandler(true);

        // all done - pass back the new object
        return $storage;
    }


    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        foreach ($this->logs as $log) {
            $log->log($level, $message, $context);
        }
    }
}

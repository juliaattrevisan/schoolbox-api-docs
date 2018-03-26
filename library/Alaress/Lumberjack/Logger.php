<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Lumberjack;


use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monologger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface {

    const FIELD_MEM       = 'mem';
    const FIELD_TRUE_MEM  = 'truemem';
    const FIELD_PID       = 'pid';
    const FIELD_LEVEL     = 'level';
    const FIELD_PORCELAIN = 'porcelain';

    /**
     * @return array
     */
    public static function getAvailableFields() {
        return array(
            self::FIELD_LEVEL,
            self::FIELD_PID,
            self::FIELD_MEM,
            self::FIELD_TRUE_MEM,
        );
    }


    /** @var resource */
    protected $out;

    /** @var resource */
    protected $err;

    /** @var Monologger */
    protected $log;

    /** @var HandlerInterface[] */
    protected $handlers;

    /** @var bool */
    protected $porcelain;

    /** @var int */
    protected $level;


    /**
     * @param resource $outStream
     * @param resource $errStream
     * @param int      $threshold
     * @param array    $fields
     * @param bool     $porcelain
     */
    public function __construct($outStream = STDOUT, $errStream = null, $threshold = 200, $fields = array(), $porcelain = false) {

        $this->out = $outStream;
        $this->err = $errStream;
        $this->porcelain = $porcelain;


        $processors = array(new MicrotimeProcessor());

        if(in_array(self::FIELD_MEM, $fields)) {
            $processors[] = new MemoryUsageProcessor(false);
            $processors[] = new MemoryPeakUsageProcessor(false);
        }

        if(in_array(self::FIELD_TRUE_MEM, $fields)) {
            $processors[] = new MemoryUsageProcessor(true);
            $processors[] = new MemoryPeakUsageProcessor(true);
        }

        if(in_array(self::FIELD_PID, $fields)) {
            $processors[] = new ProcessIdProcessor();
        }

        $this->log = new Monologger('cli', array(), $processors);
        $this->setLevel($threshold);

    }


    /**
     * @param int $level
     */
    private function setLevel($level) {
        $this->handlers = array();
        // attach STDOUT
        if(is_resource($this->out)) {
            $handler = new StreamHandler($this->out, $level);
            $handler->setFormatter(new TtyFormatter($this->out, $this->porcelain));
            $this->pushHandler($handler);
        }

        // attach STDERR
        if(is_resource($this->err)) {
            $handler = new StreamHandler($this->err, max(Monologger::CRITICAL, $level));
            $handler->setFormatter(new TtyFormatter($this->out, $this->porcelain, true));
            $this->pushHandler($handler);
        }

        $this->level = $level;
    }

    /**
     * Push a new handler into log stack
     * @param HandlerInterface $handler
     */
    public function pushHandler(HandlerInterface $handler) {
        $this->log->pushHandler($handler);
    }

    /**
     * @return int
     */
    public function getThreshold()
    {
        return $this->level;
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
        $this->log(Monologger::EMERGENCY, $message, $context);

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
        $this->log(Monologger::ALERT, $message, $context);
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
        $this->log(Monologger::CRITICAL, $message, $context);
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
        $this->log(Monologger::ERROR, $message, $context);
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
        $this->log(Monologger::WARNING, $message, $context);
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
        $this->log(Monologger::NOTICE, $message, $context);
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
        $this->log(Monologger::INFO, $message, $context);
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
        $this->log(Monologger::DEBUG, $message, $context);
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
        if (count($this->handlers) == 0) {
            // no handler spec'd - don't try to do anything
            return;
        }

        try {
            $this->log->log($level, $message, $context);
        } catch (\LogicException $e) {
            // output stream has already been closed :/
        }
    }

}

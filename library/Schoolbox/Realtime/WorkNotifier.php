<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime;

use Alaress\Lumberjack\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use Serializable;

abstract class WorkNotifier
{
    /** @var IConfigLoader */
    protected $conf;

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     */
    public function __construct($configLoader = null, $log = null)
    {
        // TODO SA-8354: inject this always
        $this->conf = $configLoader ?: new Loader();

        if ($log) {
            $this->log = $log;
        }
    }


    /**
     * @param LoggerInterface $debug
     */
    public function setLogger($debug)
    {
        $this->log = $debug;
    }


    /**
     * @return LoggerInterface
     */
    public function log()
    {
        // lazyload
        if (empty($this->log)) {
            $this->log = new Logger(STDOUT, STDERR);
        }
        return $this->log;
    }


    /**
     * This method is executed by a worker
     *
     * @param object $object the object to process
     * @return int the number of actions taken
     */
    abstract public function process($object);


    /**
     * Array representation of object
     *
     * @return array
     */
    public function __serialize(): array
    {
        // no serialization
        return [];
    }


    /**
     * Constructs the object
     *
     * @param array $serialized
     *
     * @return void
     */
    public function __unserialize(array $serialized): void
    {
        // no serialization
        $this->__construct();
    }
}

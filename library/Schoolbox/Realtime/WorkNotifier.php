<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;


use Alaress\Lumberjack\Logger;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use Serializable;

abstract class WorkNotifier implements Serializable {

    /** @var IConfigLoader */
    protected $conf;

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     */
    public function __construct($configLoader = null, $log = null) {
        $this->conf = $configLoader ?: new Loader();

        if ($log) {
            $this->log = $log;
        }
    }


    /**
     * @param LoggerInterface $debug
     */
    public function setLogger($debug) {
        $this->log = $debug;
    }


    /**
     * @return LoggerInterface
     */
    public function log()
    {
        // lazyload
        if (empty($this->log)) {
            $this->log = new Logger(null);
        }
        return $this->log;
    }


    /**
     * This method is executed by a worker
     *
     * @param object $object the object to process
     * @return int the number of actions taken
     */
    public abstract function process($object);


    /**
     * @return string the string representation of the object or null
     */
    public function serialize() {
        // no serialization
        return get_class($this);
    }


    /**
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized) {
        // no serialization
        $this->__construct();
    }

}

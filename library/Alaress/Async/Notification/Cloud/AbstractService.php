<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Notification\Cloud;


use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class AbstractService implements ServiceInterface
{

    /**
     * Yes, this is private on purpose. See the protected accessor for the why.
     *
     * @var LoggerInterface
     */
    private $log;


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function setLog(LoggerInterface $log)
    {
        $this->log = $log;
        return $this;
    }


    /**
     * @return LoggerInterface
     */
    protected function getLog()
    {
        // ensure there's at least a dummy logger there
        if (empty($this->log)) {
            $this->setLog(new Logger(null));
        }
        return $this->log;
    }

}

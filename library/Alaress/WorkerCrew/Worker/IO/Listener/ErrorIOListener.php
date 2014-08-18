<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;


use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\WorkerCrew\PoolInterface;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;

class ErrorIOListener extends LogIOListener
{

    /** @var PoolInterface */
    protected $pool;


    /**
     * @param LoggerInterface $log
     * @param PoolInterface   $pool
     */
    public function __construct(LoggerInterface $log, PoolInterface $pool)
    {
        parent::__construct($log);
        $this->pool = $pool;
    }


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        $this->log->error($e->getData());

        // draw the hard line: you get data down this pipe, say goodbye to the worker
        try {
            $this->pool->releaseWorker($this->pool->getProcessIdByResource($e->getStream()));

        } catch(OutOfBoundsException $oob) {
            $this->log->warning("Could not release worker with estream [:{$e->getStream()}]");
        }
    }

}
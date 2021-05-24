<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;

use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Async\Listener\Stream\StreamListener;
use Psr\Log\LoggerInterface;

abstract class AbstractIOStreamListener implements StreamListener
{

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param LoggerInterface $log
     */
    public function __construct($log)
    {
        $this->log = $log;
    }


    /**
     * Sent when the stream is successfully connected
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamOpened(StreamEvent $e)
    {
        $this->log->debug(sprintf('stream [:%d] open', (int)$e->getStream()));
    }


    /**
     * Sent when the stream is drained
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamDrained(StreamEvent $e)
    {
        // noop
    }


    /**
     * Sent when an error occurs in the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function errorOccurred(StreamEvent $e)
    {
        $msg = sprintf('stream [:%d] error: %s', $e->getStream(), $e->getData());
        $this->log->error($msg, array($e->getData()));
    }
}

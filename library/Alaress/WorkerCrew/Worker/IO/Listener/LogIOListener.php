<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker\IO\Listener;

use Alaress\Async\Listener\Stream\StreamEvent;

class LogIOListener extends AbstractIOStreamListener
{

    /**
     * Sent when the stream has been closed for any reason
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamClosed(StreamEvent $e)
    {
        $this->log->debug(sprintf('lstream [:%d] close', $e->getStream()));
    }


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        $this->log->notice($e->getData());
    }
}

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

class ErrorIOListener extends LogIOListener
{

    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e)
    {
        $this->log->error($e->getData());
    }

}
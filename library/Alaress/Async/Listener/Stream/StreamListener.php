<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Listener\Stream;

use Alaress\Async\Listener\EventListener;

interface StreamListener extends EventListener
{

    /**
     * Sent when the stream is successfully connected
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamOpened(StreamEvent $e);


    /**
     * Sent when the stream has been closed for any reason
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamClosed(StreamEvent $e);


    /**
     * Sent when the stream is drained
     *
     * @param StreamEvent $e
     * @return void
     */
    public function streamDrained(StreamEvent $e);


    /**
     * Sent when data is received through the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function dataReceived(StreamEvent $e);


    /**
     * Sent when an error occurs in the stream
     *
     * @param StreamEvent $e
     * @return void
     */
    public function errorOccurred(StreamEvent $e);
}

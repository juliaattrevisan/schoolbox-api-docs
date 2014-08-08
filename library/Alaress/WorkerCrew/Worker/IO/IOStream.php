<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker\IO;

use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Async\Listener\Stream\StreamListener;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

/**
 * Class WorkerIOStream that tries to safely encapsulate React's "looseness"
 *
 * @package Alaress\WorkerCrew\Worker\IO
 */
class IOStream extends Stream
{

    /**
     * @param resource       $stream
     * @param LoopInterface  $loop
     * @param StreamListener $listener
     */
    public function __construct($stream, LoopInterface $loop, StreamListener $listener)
    {
        parent::__construct($stream, $loop);

        $self = $this;

        $this->on(
             'data',
                 function ($data, $reactStream) use ($listener, $self) {
                     // confirm valid resource
                     if (!is_resource($reactStream->stream)) {
                         $self->close();
                         return;
                     }

                     // attempt to ensure that this stream isn't blocking
                     // stream_set_blocking($reactStream->stream, 0);

                     $e = new StreamEvent($reactStream->stream, $data);

                     // don't send null data down the pipe
                     if ($e->isStreamEnded() || strlen($data) === 0) {
                         $self->close();
                         return;
                     }

                     $listener->dataReceived($e);
                 }
        );

        $this->on(
             'close',
                 function ($reactStream) use ($listener) {
                     $listener->streamClosed(new StreamEvent($reactStream->stream));
                 }
        );

        $this->on(
             'error',
                 function ($error, $reactStream) use ($listener) {
                     $listener->errorOccurred(new StreamEvent($reactStream->stream, $error));
                 }
        );

        $this->on(
             'drain',
                 function () use ($listener) {
                     $listener->streamDrained(new StreamEvent(null));
                 }
        );

        $listener->streamOpened(new StreamEvent($stream));
    }

}
<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker\IO;

use Alaress\Async\Listener\EventListener;
use Alaress\Async\Listener\Listenable;
use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Async\Listener\Stream\StreamListener;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use SplObjectStorage;

/**
 * Class WorkerIOStream that tries to safely encapsulate React's "looseness"
 *
 * @package Alaress\WorkerCrew\Worker\IO
 */
class IOStream extends Stream implements Listenable
{

    /** @var SplObjectStorage|StreamListener[] */
    protected $listenerStore;

    /**
     * @param resource       $stream
     * @param LoopInterface  $loop
     * @param StreamListener $listener
     */
    public function __construct($stream, LoopInterface $loop, StreamListener $listener = null)
    {
        parent::__construct($stream, $loop);
        $this->listenerStore = new SplObjectStorage();

        if($listener) {
            $this->attachListener($listener);
        }

        $listenerStore = $this->getListeners();

        $this->on(
             'data',
                 function ($data, $reactStream) use ($listenerStore) {
                     /**
                      * @var string $data
                      * @var self $reactStream
                      */

                     // confirm valid resource
                     if (!is_resource($reactStream->stream)) {
                         $reactStream->close();
                         return;
                     }

                     // attempt to ensure that this stream isn't blocking
                     // stream_set_blocking($reactStream->stream, 0);

                     $e = new StreamEvent($reactStream->stream, $data);

                     // don't send null data down the pipe
                     if ($e->isStreamEnded() || strlen($data) === 0) {
                         $reactStream->close();
                         return;
                     }

                     foreach($listenerStore as $listener) {
                         $listener->dataReceived($e);
                     }
                 }
        );

        $this->on(
             'close',
                 function ($reactStream) use ($listenerStore) {
                     foreach($listenerStore as $listener) {
                         $listener->streamClosed(new StreamEvent($reactStream->stream));
                     }
                 }
        );

        $this->on(
             'error',
                 function ($error, $reactStream) use ($listenerStore) {
                     foreach($listenerStore as $listener) {
                         $listener->errorOccurred(new StreamEvent($reactStream->stream, $error));
                     }
                 }
        );

        $this->on(
             'drain',
                 function () use ($listenerStore) {
                     foreach($listenerStore as $listener) {
                         $listener->streamDrained(new StreamEvent(null));
                     }
                 }
        );

        $listener->streamOpened(new StreamEvent($stream));
    }


    /**
     * @return StreamListener[]|SplObjectStorage
     */
    protected function getListeners()
    {
        return $this->listenerStore;
    }


    /**
     * @param EventListener $listener
     * @return void
     */
    public function attachListener(EventListener $listener)
    {
        $this->listenerStore->attach($listener);
    }


    /**
     * @param EventListener $listener
     * @return void
     */
    public function detachListener(EventListener $listener)
    {
        $this->listenerStore->detach($listener);
    }

}
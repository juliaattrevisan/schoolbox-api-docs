<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\WorkerCrew\Worker\IO;

use Alaress\Async\Listener\EventListener;
use Alaress\Async\Listener\Listenable;
use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Async\Listener\Stream\StreamListener;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use SplObjectStorage;

/**
 * Class WrappedIoStream
 */
class WrappedIoStream implements Listenable
{
    /**
     * @var SplObjectStorage|StreamListener[]
     */
    private $listenerStore;


    /**
     * @param resource       $stream
     * @param LoopInterface  $loop
     * @param StreamListener $listener
     */
    public function __construct($stream, LoopInterface $loop, StreamListener $listener = null)
    {
        $streamObject        = new DuplexResourceStream($stream, $loop);
        $this->listenerStore = new SplObjectStorage();

        if ($listener) {
            $this->attachListener($listener);
        }

        $listenerStore = $this->getListeners();

        $streamObject->on(
            'data',
            static function ($data) use ($listenerStore, $streamObject, $stream) {
                /**
                 * @var string $data
                 */

                // confirm valid resource
                if (!is_resource($stream)) {
                    $streamObject->close();
                    return;
                }

                // attempt to ensure that this stream isn't blocking
                // stream_set_blocking($reactStream->stream, 0);

                $e = new StreamEvent($stream, $data);

                // don't send null data down the pipe
                if ($data === '' || $e->isStreamEnded()) {
                    $streamObject->close();
                    unset($e);
                    return;
                }

                foreach ($listenerStore as $listener) {
                    $listener->dataReceived($e);
                }

                unset($e);
            }
        );

        $streamObject->on(
            'close',
            static function () use ($listenerStore, $stream) {
                foreach ($listenerStore as $listener) {
                    $listener->streamClosed(new StreamEvent($stream));
                }
                unset($e);
            }
        );

        $streamObject->on(
            'error',
            static function ($error) use ($listenerStore, $stream) {
                foreach ($listenerStore as $listener) {
                    $listener->errorOccurred(new StreamEvent($stream, $error));
                }
                unset($e);
            }
        );

        $streamObject->on(
            'drain',
            static function () use ($listenerStore) {
                foreach ($listenerStore as $listener) {
                    $listener->streamDrained(new StreamEvent(null));
                }
                unset($e);
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

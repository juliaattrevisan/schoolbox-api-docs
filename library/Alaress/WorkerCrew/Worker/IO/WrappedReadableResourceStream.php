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
use React\Stream\ReadableResourceStream;
use SplObjectStorage;

/**
 * Wrapper around a ReadableResourceStream.
 *
 * Attempts to safely encapsulate React's "looseness".
 */
class WrappedReadableResourceStream implements Listenable
{
    /**
     * @var SplObjectStorage|StreamListener[]
     */
    private $listenerStore;


    /**
     * @var ReadableResourceStream
     */
    private $streamObject;


    /**
     * Constructor
     *
     * @param resource            $stream
     * @param LoopInterface       $loop
     * @param StreamListener|null $listener
     * @param int|null            $readChunkSize
     */
    public function __construct(
        $stream,
        LoopInterface $loop,
        StreamListener $listener = null,
        int $readChunkSize = null
    ) {
        $this->streamObject  = new ReadableResourceStream($stream, $loop, $readChunkSize);
        $this->listenerStore = new SplObjectStorage();

        if ($listener) {
            $this->attachListener($listener);
        }

        $this->streamObject->on(
            'data',
            function ($data) use ($stream) {
                /**
                 * @var string $data
                 */

                // confirm valid resource
                if (!is_resource($stream)) {
                    $this->streamObject->close();
                    return;
                }

                // attempt to ensure that this stream isn't blocking
                // stream_set_blocking($reactStream->stream, 0);

                $e = new StreamEvent($stream, $data);

                // don't send null data down the pipe
                if ($data === '' || $e->isStreamEnded()) {
                    $this->streamObject->close();
                    unset($e);
                    return;
                }

                foreach ($this->listenerStore as $listener) {
                    $listener->dataReceived($e);
                }

                unset($e);
            }
        );

        $this->streamObject->on(
            'close',
            function () use ($stream) {
                foreach ($this->listenerStore as $listener) {
                    $listener->streamClosed(new StreamEvent($stream));
                }
                unset($e);
            }
        );

        $this->streamObject->on(
            'error',
            function ($error) use ($stream) {
                foreach ($this->listenerStore as $listener) {
                    $listener->errorOccurred(new StreamEvent($stream, $error));
                }
                unset($e);
            }
        );

        $this->streamObject->on(
            'drain',
            function () {
                foreach ($this->listenerStore as $listener) {
                    $listener->streamDrained(new StreamEvent(null));
                }
                unset($e);
            }
        );

        $listener->streamOpened(new StreamEvent($stream));
    }


    /**
     * @param EventListener $listener
     * @return void
     */
    public function attachListener(EventListener $listener): void
    {
        $this->listenerStore->attach($listener);
    }


    /**
     * @param EventListener $listener
     * @return void
     */
    public function detachListener(EventListener $listener): void
    {
        $this->listenerStore->detach($listener);
    }


    /**
     * Close the wrapped stream.
     */
    public function close(): void
    {
        $this->streamObject->close();
    }
}

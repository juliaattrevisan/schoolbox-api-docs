<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\WorkerCrew\Worker\IO;

use Alaress\Async\Listener\EventListener;
use Alaress\Async\Listener\Listenable;
use Alaress\Async\Listener\Stream\StreamEvent;
use Alaress\Async\Listener\Stream\StreamListener;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use SplObjectStorage;

/**
 * Wrapper around a ReadableResourceStream.
 *
 * Attempts to safely encapsulate React's "looseness".
 */
class WrappedReadableResourceStream implements Listenable, ReadableStreamInterface
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
            function ($data) use ($stream): void {
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
            function () use ($stream): void {
                foreach ($this->listenerStore as $listener) {
                    $listener->streamClosed(new StreamEvent($stream));
                }
                unset($e);
            }
        );

        $this->streamObject->on(
            'error',
            function ($error) use ($stream): void {
                foreach ($this->listenerStore as $listener) {
                    $listener->errorOccurred(new StreamEvent($stream, $error));
                }
                unset($e);
            }
        );

        $this->streamObject->on(
            'drain',
            function (): void {
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
     * Pass-through to the wrapped stream's close() method.
     */
    public function close()
    {
        $this->streamObject->close();
    }


    /**
     * Pass-through to the wrapped stream's on() method.
     *
     * @param mixed    $event
     * @param callable $listener
     */
    public function on($event, callable $listener)
    {
        $this->streamObject->on($event, $listener);
    }


    /**
     * Pass-through to the wrapped stream's once() method.
     *
     * @param mixed    $event
     * @param callable $listener
     */
    public function once($event, callable $listener)
    {
        $this->streamObject->once($event, $listener);
    }


    /**
     * Pass-through to the wrapped stream's removeListener() method.
     *
     * @param mixed    $event
     * @param callable $listener
     */
    public function removeListener($event, callable $listener)
    {
        $this->streamObject->removeListener($event, $listener);
    }


    /**
     * Pass-through to the wrapped stream's removeAllListeners() method.
     *
     * @param mixed $event
     */
    public function removeAllListeners($event = null)
    {
        $this->streamObject->removeAllListeners($event);
    }


    /**
     * Pass-through to the wrapped stream's listeners() method.
     *
     * @param mixed $event
     */
    public function listeners($event = null)
    {
        $this->streamObject->listeners($event);
    }


    /**
     * Pass-through to the wrapped stream's emit() method.
     *
     * @param mixed $event
     * @param array $arguments
     */
    public function emit($event, array $arguments = [])
    {
        $this->streamObject->emit($event, $arguments);
    }


    /**
     * Pass-through to the wrapped stream's isReadable() method.
     *
     * @return bool
     */
    public function isReadable()
    {
        return $this->streamObject->isReadable();
    }


    /**
     * Pass-through to the wrapped stream's pause() method.
     */
    public function pause()
    {
        $this->streamObject->pause();
    }


    /**
     * Pass-through to the wrapped stream's resume() method.
     */
    public function resume()
    {
        $this->streamObject->resume();
    }


    /**
     * Pass-through to the wrapped stream's WritableStreamInterface() method.
     *
     * @param WritableStreamInterface $dest
     * @param array                   $options
     * @return WritableStreamInterface
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return $this->streamObject->pipe($dest, $options);
    }
}

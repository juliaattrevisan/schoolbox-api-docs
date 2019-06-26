<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\StompPhp;

use Alaress\Async\Stomp\IClient;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Stomp\Exception\StompException;
use Stomp\StatefulStomp;
use Stomp\Transport\Frame as BaseFrame;

/**
 * Adapter for the stomp-php/stomp-php Stomp client.
 */
class Client implements IClient
{
    /**
     * @var StatefulStomp
     */
    private $client;


    /**
     * @var LoopInterface
     */
    private $loop;


    /**
     * @var callable[]
     */
    private $callbacks = [];


    /**
     * Is this client actually reading frames?
     *
     * @var bool
     */
    private $readingFrames = false;


    /**
     * The max number of frames to attempt to read per tick.
     */
    private const MAX_FRAMES_PER_TICK = 10;


    /**
     * Constructor
     *
     * @param StatefulStomp $client
     * @param LoopInterface $loop
     */
    public function __construct(StatefulStomp $client, LoopInterface $loop)
    {
        $this->client = $client;
        $this->loop   = $loop;
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->stopReadingFrames();
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $subscriptionId = $this->client->subscribe($destination, null, $ackMode, $headers);
        $this->startReadingFrames();

        // TODO figure out what to do with $callback
        // react/stomp uses an input stream, which I think asynchronously
        // listens for frames?
        // I may need React's timer class? React/stomp uses evenement, which
        // seems to provide async timers?
        // Digging deep: it uses a pipe?
        $this->callbacks[$subscriptionId] = $callback;
        return $subscriptionId;
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();

        // As far as I can tell: connection is synchronous
        try {
            $this->client->getClient()->connect();
            $deferred->resolve($this);
        } catch (StompException $e) {
            $deferred->reject($e);
        }

        return $deferred->promise();
    }


    /**
     * Loop event for reading frames.
     */
    private function readNextFrame(): void
    {
        if (!$this->readingFrames) {
            return;
        }

        // Batch a set of frames together, and process them all at once
        /** @var BaseFrame[] $frames */
        $frames = [];
        while (count($frames) < self::MAX_FRAMES_PER_TICK) {
            $frame = $this->client->read();
            if (!$frame instanceof BaseFrame) {
                break;
            }
            $frames[] = $frame;
        }


//        $frame = $this->client->read();

        // Run this again next tick
        $this->loop->futureTick(function () {
            $this->readNextFrame();
        });

        // No frame?
//        if (!$frame instanceof BaseFrame) {
//            return;
//        }

        foreach ($frames as $frame) {
            $subscriptionId = $frame['subscription'];
            $resolver       = new AckResolver($this->client, $frame);
            if (isset($this->callbacks[$subscriptionId])) {
                $this->callbacks[$subscriptionId](new Frame($frame), $resolver);
            }

            // Did nothing accept the frame? Nack the message?
            // (Maybe doesn't matter because there's only one subscriber)
        }
    }


    /**
     * Start reading frames from the client.
     */
    private function startReadingFrames(): void
    {
        if (!$this->readingFrames) {
            $this->readingFrames = true;
            $this->loop->nextTick(function () {
                $this->readNextFrame();
            });
        }
    }


    /**
     * Stop reading frames from the client.
     */
    private function stopReadingFrames(): void
    {
        $this->readingFrames = false;
    }
}

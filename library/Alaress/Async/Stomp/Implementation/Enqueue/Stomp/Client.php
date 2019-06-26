<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Enqueue\Stomp;

use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\Implementation\Enqueue\AckResolver;
use Alaress\Async\Stomp\Implementation\Enqueue\Frame;
use Enqueue\Stomp\StompConnectionFactory;
use Enqueue\Stomp\StompContext;
use Interop\Queue\Consumer;
use Interop\Queue\Message;
use Interop\Queue\SubscriptionConsumer;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

/**
 * Adapter for the Enqueue Stomp client.
 */
class Client implements IClient
{
    /**
     * @var StompConnectionFactory
     */
    private $factory;


    /**
     * @var StompContext
     */
    private $context;


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
     * @param StompConnectionFactory $factory
     * @param LoopInterface          $loop
     */
    public function __construct(StompConnectionFactory $factory, LoopInterface $loop)
    {
        $this->factory = $factory;
        $this->loop    = $loop;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $queue = $this->context->createQueue($destination);
        $queue->setAutoDelete(false);
        $queue->setDurable(true);
        $consumer = $this->context->createConsumer($queue);
        $consumer->setAckMode($ackMode);

        $this->startReadingFrames($consumer, $callback);
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        try {
            $this->context = $this->factory->createContext();
            $deferred->resolve($this);
        } catch (Throwable $e) {
            $deferred->reject($e);
        }
        return $deferred->promise();
    }


    /**
     * Loop event for reading frames.
     *
     * @param Consumer $consumer
     * @param callable $callback
     */
    private function readNextFrame(Consumer $consumer, callable $callback): void
    {
        if (!$this->readingFrames) {
            return;
        }

        // Batch a set of messages together, and process them all at once
        /** @var Message[] $messages */
        $messages = [];
        while (count($messages) < self::MAX_FRAMES_PER_TICK) {
            $message = $consumer->receiveNoWait();
            if (!$message instanceof Message) {
                break;
            }
            $messages[] = $message;
        }


        // Run this again next tick
        $this->loop->futureTick(function () use ($consumer, $callback) {
            $this->readNextFrame($consumer, $callback);
        });

        foreach ($messages as $message) {
            $resolver = new AckResolver($consumer, $message);
            $callback(new Frame($message), $resolver);

            // Did nothing accept the message? Nack the message?
            // (Maybe doesn't matter because there's only one subscriber)
        }
    }


    /**
     * Start reading frames from the client.
     *
     * @param Consumer $consumer
     * @param callable $callback
     */
    private function startReadingFrames(Consumer $consumer, callable $callback): void
    {
        $this->readingFrames = true;
        $this->loop->nextTick(function () use ($consumer, $callback) {
            $this->readNextFrame($consumer, $callback);
        });
    }


    /**
     * Stop reading frames from the client.
     */
    private function stopReadingFrames(): void
    {
        $this->readingFrames = false;
    }
}

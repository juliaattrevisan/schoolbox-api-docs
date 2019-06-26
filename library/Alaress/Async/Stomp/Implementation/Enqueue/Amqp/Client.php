<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Enqueue\Amqp;

use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\Implementation\Enqueue\AckResolver;
use Alaress\Async\Stomp\Implementation\Enqueue\Frame;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\SubscriptionConsumer;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

/**
 * Adapter for the Enqueue AMQP client.
 */
class Client implements IClient
{
    /**
     * @var AmqpConnectionFactory
     */
    private $factory;


    /**
     * @var Context
     */
    private $context;


    /**
     * @var LoopInterface
     */
    private $loop;


    /**
     * @var SubscriptionConsumer[]
     */
    private $subscribers = [];


    /**
     * Constructor
     *
     * @param AmqpConnectionFactory $factory
     * @param LoopInterface         $loop
     */
    public function __construct(AmqpConnectionFactory $factory, LoopInterface $loop)
    {
        $this->factory = $factory;
        $this->loop    = $loop;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $queue      = $this->context->createQueue($destination);
        $consumer   = $this->context->createConsumer($queue);


        // TODO consider a polling fallback for contexts which don't support subscription

        $subscriber = $this->context->createSubscriptionConsumer();


        $this->subscribers[] = $subscriber;

        $this->loop->nextTick(static function () use ($subscriber, $consumer, $callback) {
            // Do this in a react loop
            $subscriber->subscribe($consumer, static function (Message $message, Consumer $consumer) use ($callback) {
                $callback(new Frame($message), new AckResolver($consumer, $message));
            });
            $subscriber->consume();
        });

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
}

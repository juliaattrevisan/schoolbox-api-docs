<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Enqueue\Amqp;

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Message;
use Interop\Queue\SubscriptionConsumer;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stomp\AckResolver as BaseAckResolver;
use React\Stomp\Client as BaseClient;
use React\Stomp\Protocol\Frame as BaseFrame;
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
     * Constructor
     *
     * @param AmqpConnectionFactory $factory
     */
    public function __construct(AmqpConnectionFactory $factory)
    {
        $this->factory = $factory;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $queue      = $this->context->createQueue($destination);
        $consumer   = $this->context->createConsumer($queue);
        $subscriber = $this->context->createSubscriptionConsumer();
        $subscriber->subscribe($consumer, static function (Message $message, Consumer $consumer) use ($callback) {
            $callback(new Frame($message), new AckResolver($consumer, $message));
        });
        $subscriber->consume();
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

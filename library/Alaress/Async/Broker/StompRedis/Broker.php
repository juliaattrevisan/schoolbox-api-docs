<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker\StompRedis;

use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Broker\IBroker;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\PayloadBrokenException;
use Alaress\Async\Broker\PayloadMissingException;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IAckResolver;
use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\IFrame;
use Alaress\Async\Stomp\Implementation\Stream\Connection;
use Alaress\Sentry\Tracing\Tracer;
use Predis\ClientInterface;
use Sentry\Tracing\SpanStatus;

/**
 * A STOMP-Redis implementation of the Broker. Data payloads are stored in Redis,
 * and AMQP-over-STOMP is used for the brokering queue.
 *
 * @package Alaress\Async\Broker\StompRedis
 */
class Broker implements IBroker
{
    /**
     * Constructor
     *
     * @param string          $queue      Name of the queue
     * @param string          $instanceId The instance on which this broker is listening
     * @param AMQPInterface   $amqp       an instance of the AMQP client interface
     * @param ClientInterface $redis      an instance of the Predis client interface
     * @param IClient|null    $stomp      an instanceof the STOMP client interface
     *                                    (only needs to be supplied if attaching a consumer, otherwise can be omitted)
     * @param Tracer|null     $tracer     The Sentry transaction tracer.
     */
    public function __construct(
        private string $queue,
        private string $instanceId,
        private AMQPInterface $amqp,
        private ClientInterface $redis,
        private ?IClient $stomp = null,
        private ?Tracer $tracer = null,
    ) {
        // ensure the queue requested exists so we can publish to it (via the default exchange)
        $amqp->declareQueue($this->queue);
    }


    /**
     * @param IConsumer $consumer
     * @return void
     * @throws ConnectionException
     */
    public function registerConsumer(IConsumer $consumer): void
    {
        if (null === $this->stomp) {
            throw new ConnectionException('No STOMP connection exists');
        }

        try {
            $this->stomp->subscribeWithAck(
                $this->queue,
                IClient::ACK_MODE_CLIENT_INDIVIDUAL,
                function (IFrame $frame, IAckResolver $resolver, Connection $conn, string $subscriptionId) use ($consumer): void {
                    $this->tracer?->startTransaction();

                    $resolve = new Resolver($resolver);

                    try {
                        [$key, $context] = json_decode($frame->getBody(), true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        // if the JSON decode fails, fall back to assuming frame.body == key
                        $key     = $frame->getBody();
                        $context = null;
                    }

                    $this->tracer?->setTransactionContextForDaemon($conn->getIdentifier(), $subscriptionId, $key);

                    // payload from STOMP is a key for collecting a redis payload
                    if (!$this->redis->exists($key)) {
                        $consumer->onException(new PayloadMissingException($key, $resolve));
                        $this->tracer?->finishTransaction(status: SpanStatus::internalError());
                        return;
                    }

                    try {
                        $gzip   = $this->redis->get($key);
                        $serial = gzuncompress($gzip);
                        if ($serial === false) {
                            // the payload is likely to be broken
                            $consumer->onException(new PayloadBrokenException($key, $resolve));
                            $this->tracer?->finishTransaction(status: SpanStatus::internalError());
                            return;
                        }
                        $payload = unserialize($serial);
                        if ($payload === false) {
                            // the payload is likely to be broken
                            $consumer->onException(new PayloadBrokenException($key, $resolve));
                            $this->tracer?->finishTransaction(status: SpanStatus::internalError());
                            return;
                        }
                    } catch (\TypeError $e) {
                        $consumer->onException(new PayloadBrokenException($key, $resolve, 0, $e));
                        $this->tracer?->finishTransaction(status: SpanStatus::internalError());
                        return;
                    } catch (\Exception $e) {
                        $consumer->onException($e);
                        $this->tracer?->finishTransaction(status: SpanStatus::internalError());
                        return;
                    }

                    $consumer->onMessage($payload, $resolve, $context);
                    $this->tracer?->finishTransaction();
                }
            );
        } catch (\Exception $e) {
            $consumer->onException($e);
        }
    }


    /**
     * @param mixed      $serializable anything that can be serialized
     * @param array|null $context
     * @param int|null   $ttl
     * @return void
     */
    public function publish(mixed $serializable, array $context = null, ?int $ttl = null): void
    {
        [$storageKey, $gzip] = RedisQueue::getStorage($serializable, $this->instanceId);
        $routingKey = $this->getRoutingKey();
        $exchange   = $this->getExchangeName();

        // drop the data into redis...
        if ($ttl) {
            $this->redis->setex($storageKey, $ttl, $gzip);
        } else {
            $this->redis->set($storageKey, $gzip);
        }

        $amqpPayload = $context ? json_encode([$storageKey, $context]) : $storageKey;

        // ...then advise AMQP to pick it up (on the default exchange)
        $this->amqp->publish(
            $amqpPayload,
            $routingKey,
            [
                'exchange'   => $exchange,
                'expiration' => $ttl,
            ],
        );
    }


    /**
     * @return string
     */
    protected function getRoutingKey(): string
    {
        return $this->queue; // the routing key is simply the queue's name
    }


    /**
     * @return string
     */
    protected function getExchangeName(): string
    {
        return ''; // we want to use AMQP default exchange
    }
}

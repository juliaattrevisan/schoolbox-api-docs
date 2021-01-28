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
use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IAckResolver;
use Alaress\Async\Stomp\IClient;
use Alaress\Async\Stomp\IFrame;
use Predis\ClientInterface;

/**
 * A STOMP-Redis implementation of the Broker. Data payloads are stored in Redis,
 * and AMQP-over-STOMP is used for the brokering queue.
 *
 * @package Alaress\Async\Broker\StompRedis
 */
abstract class Broker implements IBroker
{
    /** @var IClient */
    private $stomp;

    /** @var ClientInterface */
    private $redis;

    /** @var string */
    private $queue;

    /** @var AMQPInterface */
    private $amqp;


    /**
     * @param string          $queue   the name of the queue to deposit into in AMQP
     * @param AMQPInterface   $amqp    an instance of the AMQP client interface
     * @param ClientInterface $redis   an instance of the Predis client interface
     * @param IClient         $stomp   an instanceof the STOMP client interface
     *                                 (only needs to be supplied if attaching a consumer, otherwise can be omitted)
     */
    public function __construct($queue, AMQPInterface $amqp, ClientInterface $redis, IClient $stomp = null)
    {
        $this->queue = $queue;
        $this->redis = $redis;
        $this->amqp  = $amqp;
        $this->stomp = $stomp;

        // ensure the queue requested exists so we can publish to it (via the default exchange)
        $amqp->declareQueue($queue);
    }


    /**
     * @param IConsumer $consumer
     * @return void
     * @throws ConnectionException
     */
    public function registerConsumer(IConsumer $consumer)
    {
        if (null === $this->stomp) {
            throw new ConnectionException('No STOMP connection exists');
        }

        try {
            $this->stomp->subscribeWithAck(
                $this->queue,
                IClient::ACK_MODE_CLIENT_INDIVIDUAL,
                function (IFrame $frame, IAckResolver $resolver) use ($consumer) {
                    $resolve = new Resolver($resolver);

                    try {
                        [ $key, $context ] = json_decode($frame->getBody(), true);
                    } catch (\Throwable $e) {
                        // if the JSON decode fails, fall back to assuming frame.body == key
                        $key     = $frame->getBody();
                        $context = null;
                    }

                    // payload from STOMP is a key for collecting a redis payload
                    if (!$this->redis->exists($key)) {
                        $consumer->onException(new PayloadMissingException($key, $resolve));
                        return;
                    }

                    try {
                        $gzip   = $this->redis->get($key);
                        $serial = gzuncompress($gzip);
                        if ($serial === false) {
                            // the payload is likely to be broken
                            $consumer->onException(new PayloadBrokenException($key, $resolve));
                            return;
                        }
                        $payload = unserialize($serial);
                        if ($payload === false) {
                            // the payload is likely to be broken
                            $consumer->onException(new PayloadBrokenException($key, $resolve));
                            return;
                        }
                    } catch (\TypeError $e) {
                        $consumer->onException(new PayloadBrokenException($key, $resolve, 0, $e));
                        return;
                    } catch (\Exception $e) {
                        $consumer->onException($e);
                        return;
                    }

                    $consumer->onMessage($payload, $resolve, $context);
                }
            );
        } catch (\Exception $e) {
            $consumer->onException($e);
        }
    }


    /**
     * @param mixed $serializable anything that can be serialized
     * @param array $context
     * @param int $ttl
     * @return void
     */
    public function publish($serializable, array $context = null, int $ttl = null)
    {
        $serial = serialize($serializable);
        $gzip   = gzcompress($serial, 9);
        $hash   = sha1($gzip);

        $storageKey = $this->getStorageKey($serializable, $hash);
        $routingKey = $this->getRoutingKey();
        $exchange   = $this->getExchangeName();

        // drop the data into redis...
        if ($ttl) {
            $this->redis->setex($storageKey, $ttl, $gzip);
        } else {
            $this->redis->set($storageKey, $gzip);
        }

        $amqpPayload = $context ? json_encode([ $storageKey, $context ]) : $storageKey;

        // ...then advise AMQP to pick it up (on the default exchange)
        $this->amqp->publish($amqpPayload, $routingKey, [ 'exchange' => $exchange ]);
    }


    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }


    /**
     * @param mixed  $serializable
     * @param string $hash
     * @return string
     */
    abstract protected function getStorageKey($serializable, $hash);


    /**
     * @return string
     */
    abstract protected function getRoutingKey();


    /**
     * @return string
     */
    abstract protected function getExchangeName();
}

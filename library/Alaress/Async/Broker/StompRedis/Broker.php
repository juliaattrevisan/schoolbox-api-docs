<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Async\Broker\StompRedis;

use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Broker\IBroker;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\PayloadBrokenException;
use Alaress\Async\Broker\PayloadMissingException;
use Predis\ClientInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Protocol\Frame;


/**
 * A STOMP-Redis implementation of the Broker. Data payloads are stored in Redis,
 * and AMQP-over-STOMP is used for the brokering queue.
 *
 * @package Alaress\Async\Broker\StompRedis
 */
abstract class Broker implements IBroker
{
    const ACK_MODE = 'client-individual';

    /** @var Client */
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
     * @param Client          $stomp   an instanceof the STOMP client interface
     *                                 (only needs to be supplied if attaching a consumer, otherwise can be omitted)
     */
    public function __construct($queue, AMQPInterface $amqp, ClientInterface $redis, Client $stomp = null)
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
     * @throws \React\Stomp\Exception\ConnectionException
     * @return void
     */
    public function registerConsumer(IConsumer $consumer)
    {
        if (null === $this->stomp) {
            throw new ConnectionException('No STOMP connection exists');
        }

        try {
            $this->stomp->subscribeWithAck($this->queue, self::ACK_MODE, function (Frame $frame, AckResolver $resolver) use ($consumer) {

                $resolve = new Resolver($resolver);

                try {
                    [ $key, $context ] = json_decode($frame->body, true);

                } catch (\Throwable $e) {
                    // if the JSON decode fails, fall back to assuming frame.body == key
                    $key     = $frame->body;
                    $context = null;
                }

                // payload from STOMP is a key for collecting a redis payload
                if (!$this->redis->exists($key)) {
                    $consumer->onException(new PayloadMissingException($key, $resolve));
                    return;
                }

                try {
                    $gzip    = $this->redis->get($key);
                    $serial  = gzuncompress($gzip);
                    $payload = unserialize($serial);

                } catch (\Throwable $e) {
                    $consumer->onException(new PayloadBrokenException($key, $resolve));
                    return;
                }

                $consumer->onMessage($payload, $resolve, $context);
            });

        } catch (\Exception $e) {
            $consumer->onException($e);
        }
    }


    /**
     * @param mixed $serializable anything that can be serialized
     * @param array $context
     * @return void
     */
    public function publish($serializable, array $context = null)
    {
        $serial = serialize($serializable);
        $gzip   = gzcompress($serial, 9);
        $hash   = sha1($gzip);

        $storageKey = $this->getStorageKey($serializable, $hash);
        $routingKey = $this->getRoutingKey();
        $exchange   = $this->getExchangeName();

        // drop the data into redis...
        $this->redis->set($storageKey, $gzip);

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
    protected abstract function getStorageKey($serializable, $hash);


    /**
     * @return string
     */
    protected abstract function getRoutingKey();


    /**
     * @return string
     */
    protected abstract function getExchangeName();
}
<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Stomp\Exception\StompException;
use Stomp\StatefulStomp;

/**
 * Adapter for the stomp-php/stomp-php Stomp client.
 */
class StompPhpAdapter implements IClient
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
     * @var TimerInterface
     */
    private $timer;


    /**
     * @var callable[]
     */
    private $callbacks = [];


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
        $this->timer  = $this->loop->addPeriodicTimer(
            1,
            function() {
                while (true) {
                    $frame = $this->client->read();
                    if ($frame === false) {
                        break;
                    }

                    // Check each frame against the subscription ID?
                    foreach ($this->callbacks as $subscriptionId => $callback) {
                        $callback();
                    }
                }
            }
        );
    }


    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->loop->cancelTimer($this->timer);
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $subscriptionId = $this->client->subscribe($destination, null, $ackMode, $headers);

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
}

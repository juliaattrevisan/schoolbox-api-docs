<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\React;

use Alaress\Async\Stomp\ConnectionException;
use Alaress\Async\Stomp\IClient;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stomp\AckResolver as BaseAckResolver;
use React\Stomp\Client as BaseClient;
use React\Stomp\Protocol\Frame as BaseFrame;
use Throwable;

/**
 * Adapter for the react/stomp Stomp client.
 */
class Client implements IClient
{
    /**
     * @var BaseClient
     */
    private $client;


    /**
     * Constructor
     *
     * @param BaseClient $client
     */
    public function __construct(BaseClient $client)
    {
        $this->client = $client;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        // The callback for this object will need to:
        // * provide a frame adapter
        // * provide a resolver adapter
        // and ensure the callback accepts those?

        return $this->client->subscribeWithAck(
            $destination,
            $ackMode,
            static function (BaseFrame $frame, BaseAckResolver $resolver) use ($callback) {
                // Adapt frame
                $callback(new Frame($frame), new AckResolver($resolver));
            },
            $headers
        );
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        $this->client->connect()->then(
            function () use ($deferred) {
                $deferred->resolve($this);
            },
            static function ($e) use ($deferred) {
                $deferred->reject(
                    new ConnectionException(
                        'Could not connect to STOMP server',
                        0,
                        $e instanceof Throwable ? $e : null
                    )
                );
            }
        );
        return $deferred->promise();
    }
}

<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

use React\Promise\PromiseInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;

/**
 * Adapter for the react/stomp Stomp client.
 */
class ReactStompAdapter implements IClient
{
    /**
     * @var Client
     */
    private $client;


    /**
     * Constructor
     *
     * @param Client $client
     */
    public function __construct(Client $client)
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
            function (Frame $frame, AckResolver $resolver) {

            },
            $headers
        );
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        return $this->client->connect();
    }
}

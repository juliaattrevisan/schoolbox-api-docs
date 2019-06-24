<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

use React\Promise\PromiseInterface;
use React\Stomp\Client;

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
        return $this->client->subscribeWithAck($destination, $ackMode, $callback, $headers);
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        return $this->client->connect();
    }
}

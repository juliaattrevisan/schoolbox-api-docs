<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IAckResolver;
use Alaress\Async\Stomp\IClient;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Stomp\Transport\Frame as BaseFrame;

/**
 * Implementation for a streaming STOMP client.
 */
class Client implements IClient
{
    /**
     * The connection to the Stomp server
     *
     * @var Connection
     */
    private $connection;


    /**
     * Constructor
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $subscriptionId = $this->connection->subscribe($destination, $ackMode);

        // Need to pass in a callback to subscribe method
        $this->connection->onMessage(
            $subscriptionId,
            function (BaseFrame $frame, IAckResolver $resolver) use ($callback, $subscriptionId): void {
                $callback(new Frame($frame), $resolver, $this->connection, $subscriptionId);
            }
        );
        return $subscriptionId;
    }


    /**
     * @inheritDoc
     */
    public function connect(): PromiseInterface
    {
        $deferred = new Deferred();
        $promise  = $this->connection->connect();
        $promise->then(
            function () use ($deferred): void {
                $deferred->resolve($this);
            },
            function ($error) use ($deferred): void {
                $deferred->reject($error);
            }
        );
        return $deferred->promise();
    }
}

<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

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
     * Constructor
     *
     * @param StatefulStomp $client
     */
    public function __construct(StatefulStomp $client)
    {
        $this->client = $client;
    }


    /**
     * @inheritDoc
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = [])
    {
        $subscriptionId = $this->client->subscribe($destination, null, $ackMode, $headers);

        // TODO figure out what to do with $callback
        // react/stomp uses an input stream
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
            $deferred->resolve();
        } catch (StompException $e) {
            $deferred->reject($e);
        }

        return $deferred->promise();
    }
}

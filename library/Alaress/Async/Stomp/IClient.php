<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

use Alaress\Async\Stomp\Implementation\Stream\Connection;
use React\Promise\PromiseInterface;

/**
 * Interface for Stomp clients.
 */
interface IClient
{
    /**
     * Acknowledgement mode: frames are automatically acknowledged.
     */
    public const ACK_MODE_AUTO = 'auto';

    /**
     * Acknowledgement mode: frames must be acknowledged by the client.
     */
    public const ACK_MODE_CLIENT_INDIVIDUAL = 'client-individual';

    /**
     * Subscribes to a Stomp queue.
     *
     * @param string                                                                                 $destination
     * @param string                                                                                 $ackMode
     * @param callable(IFrame $frame, IAckResolver $resolver, Connection $conn, string $subId): void $callback
     *        The callback to call upon receiving a message:
     *        * $frame:    the Stomp message frame
     *        * $resolver: the object which may resolve or reject the frame
     *        * $conn:     the underlying Stomp connection
     *        * $subId:    the subscription ID
     * @param array                                                                                  $headers
     * @return mixed the subscription ID that the Stomp queue returns
     */
    public function subscribeWithAck(string $destination, string $ackMode, callable $callback, array $headers = []);

    /**
     * Connects to a Stomp queue.
     *
     * @return PromiseInterface Resolves when the queue is connected to,
     *                          rejects if it cannot connect.
     *                          The resolve callback will take this object as
     *                          its only parameter.
     *                          The reject callback takes a ConnectionException
     *                          as its only parameter.
     */
    public function connect(): PromiseInterface;
}

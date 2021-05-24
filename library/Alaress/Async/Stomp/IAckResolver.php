<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

/**
 * Standard interface for ack resolvers.
 *
 * @package Alaress\Async\Stomp
 */
interface IAckResolver
{
    /**
     * Acknowledges a message.
     *
     * @param array $headers
     */
    public function ack(array $headers = []): void;


    /**
     * Refuses acknowledgement of a message.
     *
     * @param array $headers
     */
    public function nack(array $headers = []): void;
}

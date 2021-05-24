<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker;

/**
 * A consumer receives a payload from a Broker in an event-driven way. The consumer should handle payloads received
 * via `onMessage`, and then either `acknowledge` or `reject` the Resolver passed along with the payload:
 *  - Acknowledgement of a message denotes successful processing; and the message can be dismissed by the server.
 *  - Rejection of a message denotes failed processing; the message should be retained by the server, but this
 *    consumer is releasing it for the server to retry processing at a later time.
 *
 * @package Alaress\Async\Broker
 */
interface IConsumer
{
    /**
     * Handle a message sent from a broker to which you've subscribed
     *
     * @param mixed      $payload
     * @param IResolver  $resolver
     * @param array|null $context
     * @return void
     */
    public function onMessage($payload, IResolver $resolver, ?array $context);


    /**
     * Handle brokering exceptions
     *
     * @param \Exception $e
     * @return void
     */
    public function onException(\Exception $e);
}

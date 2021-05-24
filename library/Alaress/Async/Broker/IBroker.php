<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker;

/**
 * The Broker is a cohesive interface for publishing data to a brokering server and accepting Consumers
 * that are capable of processing payloads that are delivered to them.
 *
 * The Broker-Consumer pattern is a subset of Publisher-Subscriber: multiple Consumers can "subscribe" to a Broker,
 * however any given message will be delivered to EXACTLY ONE Consumer at any point in time (compare and contrast
 * a Publisher who publishes to ALL Subscribers registered at the time of publication). An implication of the
 * exactly-one-ness is that messages cannot be lost: if there is no Consumer ready, the message MUST be queued
 * until a Consumer becomes available to receive it.
 *
 * @package Alaress\Async\Broker
 */
interface IBroker
{
    /**
     * Subscribe the given consumer to this broker's queue.
     *
     * @param IConsumer $consumer
     * @return void
     */
    public function registerConsumer(IConsumer $consumer);


    /**
     * Publish the given payload to this broker's queue.
     *
     * @param mixed  $serializable
     * @param array  $context
     * @param int    $ttl
     * @return void
     */
    public function publish($serializable, array $context = [], int $ttl = null);
}

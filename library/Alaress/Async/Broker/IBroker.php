<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Broker;

use PhpAmqpLib\Exception\AMQPRuntimeException;

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
     * @param IConsumer $consumer An object which may read from a queue.
     * @return void
     */
    public function registerConsumer(IConsumer $consumer): void;


    /**
     * Publish the given payload to this broker's queue, declaring the queue if
     * it isn't already declared.
     *
     * @param mixed    $serializable Any message which may be pushed to a queue.
     * @param array    $context      Any context for what is being pushed: e.g.
     *                               the message's namespace, the instance from
     *                               which it was raised, the user which
     *                               triggered it.
     * @param int|null $ttl          How long should the message exist in the
     *                               queue unprocessed, before it is purged?
     * @return void
     * @throws AMQPRuntimeException if the queue cannot be declared
     */
    public function publish(mixed $serializable, array $context = [], ?int $ttl = null): void;
}

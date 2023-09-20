<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Message;

use Enqueue\Redis\RedisMessage;
use Interop\Queue\Message;

/**
 * Adapter class for getting and setting custom message headers on the base
 * message received from Enqueue (`\Interop\Queue\Message`).
 *
 * Not related to `\Schoolbox\TaskDaemon\Request\Message`: that class defines an
 * object structure for a message to be sent by the task daemon, and is created
 * from the body of an `\Interop\Queue\Message` instance.
 */
class HeaderAdapter
{
    /**
     * Custom message header: dictates how many times this message has been
     * added back to the queue.
     *
     * This custom header and the Redis attempts header refer to slightly
     * different concepts:
     * * the Redis attempt header is automatically incremented whenever a
     *   message is neither acknowledged nor rejected before the redelivery
     *   delay is reached:
     *   @see \Schoolbox\TaskDaemon\Environment::ENV_VAR_REDELIVERY_DELAY
     * * this custom header is manually incremented whenever a message is added
     *   back to its queue
     *   (e.g. when its job raised an error, or is still pending)
     */
    private const HEADER_REQUEUES = 'x-taskd-requeues';

    /**
     * Custom message header: overrides the maximum number of times a message
     * may be requeued before it is rejected.
     *
     * If not provided: this is equal to the task daemon's default, as
     * configured in the max attempts environment variable:
     * @see \Schoolbox\TaskDaemon\Environment::ENV_VAR_MAX_ATTEMPTS
     */
    private const HEADER_MAX_REQUEUES = 'x-taskd-max-requeues';

    /**
     * Set the value of the delay header in the message.
     *
     * Currently only supported in Redis queues.
     *
     * @param Message    $message
     * @param float|null $delay   The number of seconds to delay the message, or
     *                            null if no delay.
     */
    public function setDelay(Message $message, ?float $delay): void
    {
        if ($message instanceof RedisMessage) {
            $message->setDeliveryDelay(isset($delay) ? (int)($delay * 1000) : null);
        }
    }

    /**
     * Retrieve the number of times this message has been redelivered: a message
     * will be redelivered if it is delivered but neither acknowledged nor
     * rejected before the redelivery delay is reached.
     *
     * Currently only supported in Redis queues; also, the Redis queue concept
     * "attempt" has been renamed to "redelivery" here, to disambiguate from
     * requeues.
     *
     * @param Message $message
     * @return int|null
     */
    public function getRedeliveries(Message $message): ?int
    {
        return $message instanceof RedisMessage
            ? $message->getAttempts()
            : null;
    }

    /**
     * Extract the max requeues out of the message.
     *
     * @param Message $message
     * @return int|null The max number of attempts to process the message, or
     *                  null to fall back to the task daemon's default.
     */
    public function getMaxRequeues(Message $message): ?int
    {
        return filter_var($message->getHeader(self::HEADER_MAX_REQUEUES), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Set the value of the max requeues header in the message.
     *
     * @param Message  $message
     * @param int|null $maxAttempts The max number of attempts to process the
     *                              message, or null to fall back to the task
     *                              daemon's default.
     */
    public function setMaxRequeues(Message $message, ?int $maxAttempts): void
    {
        $message->setHeader(self::HEADER_MAX_REQUEUES, $maxAttempts);
    }

    /**
     * Extract the amount of times this message has been requeued.
     *
     * @param Message $message
     * @return int|null The number of attempts at processing the message, or
     *                  null if unknown.
     */
    public function getRequeues(Message $message): ?int
    {
        return filter_var($message->getHeader(self::HEADER_REQUEUES), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Increment the number of requeues of this message.
     *
     * The number of requeues should not be set directly: it should only be
     * incremented.
     *
     * @param Message $message
     * @return void
     */
    public function incrementRequeues(Message $message): void
    {
        $attempts = $this->getRequeues($message) ?? 1;
        $message->setHeader(self::HEADER_REQUEUES, $attempts + 1);
    }
}

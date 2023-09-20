<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Message;

use Enqueue\Redis\RedisMessage;
use Interop\Queue\Message;
use Schoolbox\TaskDaemon\Task\Status;

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
     * Custom message header: a chance (from 0 to 1) that the message, instead
     * of running, will raise an error.
     *
     * By default, this is 0 for no chance.
     */
    private const HEADER_TEST_ERROR_RATE = 'x-taskd-test-error-rate';

    /**
     * Custom message header: if raising an error due to
     * `HEADER_TEST_ERROR_RATE`, raise this type of error.
     *
     * Should be a `\Schoolbox\TaskDaemon\Task\Status` value.
     */
    private const HEADER_TEST_ERROR_STATUS = 'x-taskd-test-error-status';

    /**
     * Custom message header: if raising an error due to
     * `HEADER_TEST_ERROR_RATE`, raise this payload in the error.
     *
     * Should be a json-encoded array.
     */
    private const HEADER_TEST_ERROR_PAYLOAD = 'x-taskd-test-error-payload';

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

    /**
     * Extract the test error rate.
     *
     * @param Message $message
     * @return float
     */
    public function getTestErrorRate(Message $message): float
    {
        return filter_var(
            $message->getHeader(self::HEADER_TEST_ERROR_RATE),
            FILTER_VALIDATE_FLOAT,
            FILTER_NULL_ON_FAILURE,
        ) ?? 0;
    }

    /**
     * Set the test error rate.
     *
     * @param Message $message
     * @param float   $testErrorRate
     * @return void
     */
    public function setTestErrorRate(Message $message, float $testErrorRate): void
    {
        $message->setHeader(self::HEADER_TEST_ERROR_RATE, $testErrorRate);
    }

    /**
     * Extract the test error status.
     *
     * @param Message $message
     * @return Status|null
     */
    public function getTestErrorStatus(Message $message): ?Status
    {
        $status = $message->getHeader(self::HEADER_TEST_ERROR_STATUS);
        if (is_string($status)) {
            return Status::tryFrom($status);
        }
        return null;
    }

    /**
     * Set the test error status.
     *
     * @param Message     $message
     * @param Status|null $testErrorStatus
     * @return void
     */
    public function setTestErrorStatus(Message $message, ?Status $testErrorStatus): void
    {
        $message->setHeader(self::HEADER_TEST_ERROR_STATUS, $testErrorStatus?->value);
    }

    /**
     * Extract the test error payload.
     *
     * @param Message $message
     * @return array|null
     */
    public function getTestErrorPayload(Message $message): ?array
    {
        $encodedPayload = $message->getHeader(self::HEADER_TEST_ERROR_PAYLOAD);
        if (isset($encodedPayload)) {
            $decodedPayload = json_decode($encodedPayload, true);
            if (is_array($decodedPayload)) {
                return $decodedPayload;
            }
        }
        return null;
    }

    /**
     * Set the test error payload.
     *
     * @param Message    $message
     * @param array|null $testErrorPayload
     * @return void
     */
    public function setTestErrorPayload(Message $message, ?array $testErrorPayload): void
    {
        $message->setHeader(
            self::HEADER_TEST_ERROR_STATUS,
            isset($testErrorPayload) ? json_encode($testErrorPayload) : null,
        );
    }
}

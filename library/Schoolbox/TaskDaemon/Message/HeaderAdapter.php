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
     * Custom message header: dictates how many attempts have been made to send
     * this message.
     *
     * The Redis message variant supports attempts natively via a custom header,
     * but this header is removed upon rejecting and re-queueing the message.
     */
    public const HEADER_ATTEMPTS = 'x-taskd-attempts';

    /**
     * Custom message header: overrides the maximum number of attempts which may
     * be made to process a message.
     */
    public const HEADER_MAX_ATTEMPTS = 'x-taskd-max-attempts';

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
     * Extract the max attempts out of the message.
     *
     * @param Message $message
     * @return int|null The max number of attempts to process the message, or
     *                  null to fall back to the task daemon's default
     *                  (`\Schoolbox\TaskDaemon\Environment::getMaxAttempts`).
     */
    public function getMaxAttempts(Message $message): ?int
    {
        return filter_var($message->getHeader(self::HEADER_MAX_ATTEMPTS), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Set the value of the max attempts header in the message.
     *
     * @param Message  $message
     * @param int|null $maxAttempts The max number of attempts to process the
     *                              message, or null to fall back to the task
     *                              daemon's default.
     */
    public function setMaxAttempts(Message $message, ?int $maxAttempts): void
    {
        $message->setHeader(self::HEADER_MAX_ATTEMPTS, $maxAttempts);
    }

    /**
     * Extract the amount of attempts at processing out of the message.
     *
     * @param Message $message
     * @return int|null The number of attempts at processing the message, or
     *                  null if unknown.
     */
    public function getAttempts(Message $message): ?int
    {
        return filter_var($message->getHeader(self::HEADER_ATTEMPTS), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Increment the number of attempts which have been made to send this
     * message.
     *
     * The number of attempts should not be set directly: it should only be
     * incremented.
     *
     * @param Message $message
     * @return void
     */
    public function incrementAttempts(Message $message): void
    {
        $attempts = $this->getAttempts($message) ?? 1;
        $message->setHeader(self::HEADER_ATTEMPTS, $attempts + 1);
    }
}

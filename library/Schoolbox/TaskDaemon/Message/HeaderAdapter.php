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
     * Custom message header: dictates the number of seconds in the future which
     * this message is to be processed.
     */
    public const HEADER_DELAY = 'delay';

    /**
     * Custom message header: overrides the maximum number of attempts which may
     * be made to process a message.
     */
    public const HEADER_MAX_ATTEMPTS = 'max-attempts';

    /**
     * Extract the value of the delay header out of the message.
     *
     * @param Message $message
     * @return float|null The number of seconds to delay the processing of the
     *                    message, or null if no delay header exists.
     */
    public function getDelay(Message $message): ?float
    {
        return filter_var($message->getHeader(self::HEADER_DELAY), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Set the value of the delay header in the message.
     *
     * @param Message    $message
     * @param float|null $delay   The number of seconds to delay the message, or
     *                            null if no delay.
     */
    public function setDelay(Message $message, ?float $delay): void
    {
        $message->setHeader(self::HEADER_DELAY, $delay);
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
     * Only the Redis message implementation supports attempt count so far
     * (`\Enqueue\Redis\RedisMessage::getAttempts`): other implementations will
     * return null.
     *
     * Also, no setter exists: attempt counts are handled within Enqueue.
     *
     * @param Message $message
     * @return int|null The number of attempts at processing the message, or
     *                  null if unknown.
     */
    public function getAttempts(Message $message): ?int
    {
        if (!$message instanceof RedisMessage) {
            return null;
        }
        return $message->getAttempts();
    }
}

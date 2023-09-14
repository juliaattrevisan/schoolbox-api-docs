<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Message;

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
     *
     * If both this and `HEADER_PROCESS_AT` are set: `HEADER_PROCESS_AT` takes
     * precedence.
     */
    public const HEADER_DELAY = 'x-taskd-delay';

    /**
     * Custom message header: dictates the timestamp at which this message
     * should be processed.
     *
     * If this is in the future: delay processing until this time is reached,
     * otherwise process immediately.
     *
     * If both this and `HEADER_DELAY` are set: this header takes precedence.
     */
    public const HEADER_PROCESS_AT = 'x-taskd-process-at';

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
     * Extract the value of the process-at header out of the message.
     *
     * @param Message $message
     * @return float|null The timestamp at which to process the message, or null
     *                    if no such header exists.
     */
    public function getProcessAt(Message $message): ?float
    {
        return filter_var($message->getHeader(self::HEADER_PROCESS_AT), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Set the value of the process-at header in the message.
     *
     * @param Message    $message
     * @param float|null $processAt The timestamp at which to process the
     *                              message, or null if processing immediately.
     */
    public function setProcessAt(Message $message, ?float $processAt): void
    {
        $message->setHeader(self::HEADER_PROCESS_AT, $processAt);
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

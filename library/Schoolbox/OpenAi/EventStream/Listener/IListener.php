<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\EventStream\Listener;

use Schoolbox\OpenAi\EventStream\Message;

/**
 * Listener for modifying an event stream message object from OpenAI, before it
 * is forwarded to the response body.
 */
interface IListener
{
    /**
     * Modify the message received from OpenAI before it's output.
     *
     * @param Message $message The message from OpenAI.
     * @return Message|null A message to output in the response body, or null if
     *                      nothing is to be output.
     *                      This should not be the same object as `$message`,
     *                      even if it is unchanged.
     */
    public function modifyMessage(Message $message): ?Message;
}

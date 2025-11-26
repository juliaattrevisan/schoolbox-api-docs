<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler\Listener;

use Schoolbox\Editor\OpenAI\Log\Model;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Listener for updating an OpenAI log object, based on the content of the
 * message stream.
 */
interface IListener
{
    /**
     * Update log based on the message stream content.
     *
     * @param Model   $log           The log to update.
     * @param Message $outputMessage The message to be output by the AI client.
     *                               This may have been modified from its original form, before it is due to be output.
     * @param Message $rawMessage    The original message from OpenAI, before any modifications.
     * @return Model `$log`, after it's been updated.
     */
    public function updateLog(Model $log, Message $outputMessage, Message $rawMessage): Model;
}

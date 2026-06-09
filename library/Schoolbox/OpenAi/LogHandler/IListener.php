<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler;

use Schoolbox\OpenAi\Session\Log;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Listener for updating an OpenAI log object, based on the content of the
 * message stream.
 */
interface IListener
{
    /**
     * Handle this message, by writing it to a log.
     *
     * Generally, this will be responsible for:
     * * setting field values on `$log`
     *   (e.g. response code, tokens, error message)
     * * extracting the value from `$message` before it's added to the log's
     *   response
     *
     * @param Message   $outputMessage The message to output to the response body.
     *                                 This may have been modified after it was received from OpenAI, and may contain a
     *                                 structure similar to the one handled by `handleResponse`.
     * @param Message   $rawMessage    The raw message from OpenAI.
     * @param Log\Model $log           The log object to update.
     * @return Log\Model The updated log object.
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model;

    /**
     * Handle a full response from OpenAI, by writing it to a log.
     *
     * @param array     $responseData The response data from OpenAI.
     * @param Log\Model $log          The log object to update.
     * @return Log\Model The updated log object.
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model;
}

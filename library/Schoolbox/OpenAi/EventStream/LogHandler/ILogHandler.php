<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\EventStream\LogHandler;

use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Interface for log handlers for events from OpenAI.
 */
interface ILogHandler
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
     * @param Message   $message The message from OpenAI.
     *                           This may contain a data structure similar to
     *                           the one handled by `handleResponse`.
     * @param Log\Model $log     The log object to update.
     * @return Log\Model The updated log object.
     */
    public function handleMessage(Message $message, Log\Model $log): Log\Model;

    /**
     * Handle a full response from OpenAI, by writing it to a log.
     *
     * @param array     $responseData The response data from OpenAI.
     * @param Log\Model $log          The log object to update.
     * @return Log\Model The updated log object.
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model;
}

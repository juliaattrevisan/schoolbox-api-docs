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
     * @param Message   $message
     * @param Log\Model $log
     * @return Log\Model
     */
    public function handle(Message $message, Log\Model $log): Log\Model;
}

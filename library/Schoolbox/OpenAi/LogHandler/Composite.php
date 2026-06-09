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
 * Composite log handler: applies all of its handlers to the log sequentially.
 */
class Composite implements IListener
{
    /**
     * All handlers in this composite.
     *
     * @var array<IListener>
     */
    private array $handlers = [];

    /**
     * Constructor
     *
     * @param IListener ...$handlers The handlers to apply.
     */
    public function __construct(IListener ...$handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * @inheritdoc
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
        foreach ($this->handlers as $handler) {
            $log = $handler->handleMessage($outputMessage, $rawMessage, $log);
        }
        return $log;
    }

    /**
     * @inheritdoc
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        foreach ($this->handlers as $handler) {
            $log = $handler->handleResponse($responseData, $log);
        }
        return $log;
    }
}

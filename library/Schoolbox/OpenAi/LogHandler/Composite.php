<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler;

use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\Listener\IListener;

/**
 * Composite log handler: applies all of its handlers to the log sequentially.
 */
class Composite implements ILogHandler
{
    /**
     * All handlers in this composite.
     *
     * @var array<ILogHandler>
     */
    private array $handlers = [];

    /**
     * Constructor
     *
     * @param ILogHandler ...$handlers The handlers to apply.
     */
    public function __construct(ILogHandler ...$handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * Add a new handler to the composite.
     *
     * @param ILogHandler $handler
     * @return $this
     */
    public function addHandler(ILogHandler $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
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

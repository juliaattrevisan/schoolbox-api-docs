<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use Alaress_Date_Format;
use Schoolbox\Instance;

/**
 * Factory for building request messages.
 */
class MessageFactory
{
    /**
     * Information about the Schoolbox instance.
     */
    private Instance $instance;

    /**
     * Constructor
     *
     * @param Instance $instance
     */
    public function __construct(Instance $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Build a message.
     *
     * @param string      $task
     * @param array       $params
     * @param string|null $callbackUrl
     * @return Message
     */
    public function build(string $task, array $params, ?string $callbackUrl): Message
    {
        return new Message(
            $task,
            $params,
            Alaress_Date_Format::now()->getDateTimeObject(),
            $this->instance,
            $callbackUrl,
            null,
        );
    }
}

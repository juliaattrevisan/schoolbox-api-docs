<?php
/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Learning\Assessment\DataModel\DialogAgent\Response\Message;

use Schoolbox\OpenAi\AgentChat\MessageEventType;

/**
 * Available values for the status of a response message.
 *
 * Maps loosely to the types in `\Schoolbox\OpenAi\AgentChat\MessageEventType`:
 * see `fromMessageEventType`.
 */
enum Status: string
{
    case Ok = 'ok';
    case Error = 'error';

    /**
     * Create a status from a message's event type:
     * * `MessageEventType::COMPLETE` maps to `Status::Ok`
     * * `MessageEventType::ERROR` maps to `Status::Error`
     * * other types don't map
     *
     * @param MessageEventType $messageEventType
     * @return self|null
     */
    public static function fromMessageEventType(MessageEventType $messageEventType): ?self
    {
        return match ($messageEventType) {
            MessageEventType::COMPLETE => self::Ok,
            MessageEventType::ERROR    => self::Error,
            default                    => null,
        };
    }
}

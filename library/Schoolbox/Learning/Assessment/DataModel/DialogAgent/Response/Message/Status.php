<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message;

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
    case Pending = 'pending';
    case Error = 'error';

    /**
     * Create a status from a message's event type:
     * * `MessageEventType::COMPLETE` maps to `Status::Ok`
     * * `MessageEventType::ERROR` maps to `Status::Error`
     * * all other types map to `Status::Pending`
     *
     * @param MessageEventType $messageEventType
     * @return self
     */
    public static function fromMessageEventType(MessageEventType $messageEventType): self
    {
        return match ($messageEventType) {
            MessageEventType::COMPLETE                                                          => self::Ok,
            MessageEventType::CREATED, MessageEventType::PARTIAL_IMAGE, MessageEventType::DELTA => self::Pending,
            MessageEventType::ERROR                                                             => self::Error,
        };
    }
}

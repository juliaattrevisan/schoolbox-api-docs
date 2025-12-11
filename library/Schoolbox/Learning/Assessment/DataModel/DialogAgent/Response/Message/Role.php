<?php
/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message;

/**
 * Available values for the role associated with a response message.
 *
 * Maps to the OpenAI message roles concept:
 * see https://platform.openai.com/docs/guides/text#message-roles-and-instruction-following.
 */
enum Role: string
{
    case User = 'user';
    case Assistant = 'assistant';
}

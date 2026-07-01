<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

/**
 * How confident the AI Interaction Moderation agent is that a particular concern category is correctly identified.
 */
enum Confidence: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
}

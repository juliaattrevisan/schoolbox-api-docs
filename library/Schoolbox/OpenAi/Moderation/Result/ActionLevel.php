<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

/**
 * Suggested school response level from the AI Interaction Moderation agent.
 */
enum ActionLevel: string
{
    case NONE = 'none';
    case MONITOR = 'monitor';
    case REVIEW = 'review';
    case URGENT_REVIEW = 'urgent_review';
    case IMMEDIATE_ESCALATION = 'immediate_escalation';
}

<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Event;

/**
 * Possible statuses for an AI Interaction Moderation Event.
 */
enum Status: string
{
    case NO_ACTION_REQUIRED = 'no_action_required';
    case REQUIRES_ATTENTION = 'requires_attention';
    case FURTHER_ACTION_REQUIRED = 'further_action_required';
    case FURTHER_ACTION_TAKEN = 'further_action_taken';
}

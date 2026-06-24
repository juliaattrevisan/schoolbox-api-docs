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
    /**
     * A new incoming moderation event that was NOT flagged by the Interaction Moderation agent,
     * and therefore does not require any attention from school staff.
     * We keep track of these so that the nightly cron can detect if it needs to re-run against new AI session logs or not.
     *
     * The term "No Behaviour Flagged" is to avoid us saying that there's "no action required" when in fact the agent
     * just failed to detect a real issue (which could be a problem if schools complain that we told them it was fine when it wasn't).
     */
    case NO_BEHAVIOUR_FLAGGED = 'no_behaviour_flagged';
    /**
     * A new incoming moderation event that was flagged but has not yet been reviewed by school staff.
    */
    case REQUIRES_ATTENTION = 'requires_attention';
    /**
     * When a school staff member has reviewed and deemed that there is no further action required for this event.
     */
    case NO_ACTION_REQUIRED = 'no_action_required';
    /**
     * When a school staff member has reviewed and deemed that further action is required, but that action has not yet been taken.
     */
    case FURTHER_ACTION_REQUIRED = 'further_action_required';
    /**
     * When a school staff member resolves the 'further action required' request.
     * (workflow would be: event received as REQUIRES_ATTENTION -> deemed as FURTHER_ACTION_REQUIRED -> resolved to FURTHER_ACTION_TAKEN).
     */
    case FURTHER_ACTION_TAKEN = 'further_action_taken';
}

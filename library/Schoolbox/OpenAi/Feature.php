<?php

/**
 * The AI features that own a session, mirroring the `ai_session`.`feature` DB enum.
 * This is the authoritative place for these constants.  A duplicated enum exists for front-end code.
 * @see Admin/OpenAI/Usage/templates/payload.ts
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

enum Feature: string
{
    case CHAT = 'chat';
    case LESSON_PLAN = 'lessonplan';
    case IMAGE = 'image';
    case DIFFERENTIATION = 'differentiation';
    case DIALOG_AGENT = 'dialogagent';
    case LEARNING_ACTIVITY_CUSTOMISATION = 'learningactivitycustomisation';
    case RUBRIC_GENERATION = 'rubricgeneration';
    case AI_MODERATION_AGENT = 'aimoderationagent';

    /**
     * Whether this feature can be configured to use the inline AI moderation.
     * This is NOT the nightly cron moderation, that uses the logic ai_session_log.log_format to handle if we moderate those
     */
    public function supportsAiModeration(): bool
    {
        return match ($this) {
            self::DIALOG_AGENT => true,
            default => false,
        };
    }
}

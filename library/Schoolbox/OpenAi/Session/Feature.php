<?php

/**
 * The AI features that own a session, mirroring the `ai_session`.`feature` DB enum.
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

enum Feature: string
{
    case CHAT = 'chat';
    case LESSON_PLAN = 'lessonplan';
    case IMAGE = 'image';
    case DIFFERENTIATION = 'differentiation';
    case DIALOG_AGENT = 'dialogagent';
    case LEARNING_ACTIVITY_CUSTOMISATION = 'learningactivitycustomisation';
    case AI_MODERATION_AGENT = 'aimoderationagent';
}

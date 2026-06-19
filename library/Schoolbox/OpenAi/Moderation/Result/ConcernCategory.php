<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

/**
 * A type of concern identified by the AI Interaction Moderation agent.
 */
enum ConcernCategory: string
{
    case SELF_HARM = 'self_harm';
    case WELLBEING_CONCERN = 'wellbeing_concern';
    case BULLYING = 'bullying';
    case HARASSMENT = 'harassment';
    case HATE_SPEECH = 'hate_speech';
    case ABUSE = 'abuse';
    case VIOLENCE = 'violence';
    case THREAT = 'threat';
    case SEXUAL_CONTENT = 'sexual_content';
    case EXPLOITATION = 'exploitation';
    case GROOMING = 'grooming';
    case PRIVACY_RISK = 'privacy_risk';
    case DANGEROUS_BEHAVIOUR = 'dangerous_behaviour';
    case ACADEMIC_MISUSE = 'academic_misuse';
    case AI_MISUSE = 'ai_misuse';
    case GUARDRAIL_EVASION = 'guardrail_evasion';
    case DISCIPLINE_CONCERN = 'discipline_concern';
    case OTHER = 'other';
}

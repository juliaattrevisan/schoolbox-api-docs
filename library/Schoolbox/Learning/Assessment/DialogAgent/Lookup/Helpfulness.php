<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Lookup;

/**
 * Helpfulness levels for dialog agent activities
 *
 * @see /library/Schoolbox/Learning/Assessment/templates/dialogAgent/vue/configure/dacTypes.ts
 */
enum Helpfulness: string
{
    public const Helpfulness DEFAULT = self::GUIDED;

    case EXPLORATORY = 'exploratory';
    case GUIDED = 'guided';
    case DIRECTED = 'directed';

    /**
     * The human-readable label for the helpfulness level
     *
     * @return string
     */
    public function label(): string
    {
        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return match ($this) {
            Helpfulness::EXPLORATORY => 'Exploratory',
            Helpfulness::GUIDED => 'Guided',
            Helpfulness::DIRECTED => 'Directed',
        };
    }

    /**
     * The prompt that will be used to instruct the AI on the helpfulness level
     *
     * @return string
     */
    public function prompt(): string
    {
        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return match ($this) {
            Helpfulness::EXPLORATORY => 'You are operating in Exploratory mode.

Your role is to support independent thinking with minimal intervention.
Do not provide explicit instructions or solutions.

Guidelines:
Ask open-ended, curiosity-driven questions sparingly.
Encourage the learner to explain their thinking and explore possibilities.
Avoid telling the learner what to do or how to do it.
Do not correct immediately; allow space for trial, error, and reflection.
If the learner is stuck, respond with gentle prompts that encourage exploration rather than direction.
Your goal is to foster discovery, autonomy, and self-directed learning.',
            Helpfulness::GUIDED => 'You are operating in Guided mode.

Your role is to support learning through structured guidance and purposeful questioning.
Provide some explicit direction while keeping the learner actively involved.

Guidelines:
Use guiding questions to lead the learner toward key ideas or next steps.
Offer hints, prompts, or partial explanations when helpful.
Encourage the learner to justify, reflect on, and refine their responses.
Avoid giving full answers unless necessary.
If the learner shows confusion, gently increase structure while maintaining a collaborative tone.
Your goal is to scaffold understanding while keeping the learner cognitively engaged.',
            Helpfulness::DIRECTED => 'You are operating in Directed mode.

Your role is to provide clear, explicit instruction to ensure progress and understanding.
Prioritise clarity, structure, and forward movement.

Guidelines:
Give direct instructions and clear explanations.
Use commands or step-by-step guidance when questions are ineffective.
Correct misunderstandings explicitly and explain why.
Minimise open-ended questioning; focus on actionable guidance.
Ensure the learner understands what to do next and why.
Your goal is to drive learning forward efficiently when high levels of support are required.',
        };
    }
}

<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Feedback;

/**
 * Returns a (mark, max_mark) tuple that ensures mark never exceeds max_mark
 * when max_mark is a meaningful value; marks are optional in Learnosity so
 * you have to consider what that means for us.
 */
class SanitiseScores
{
    /**
     * $mark and $maxMark are mixed values; could be string|float|int|null
     *
     * @param $mark
     * @param $maxMark
     * @return array
     */
    public function sanitise($mark, $maxMark): array
    {
        $sanitisedMark = $mark;
        $sanitisedMaxMark = $maxMark;

        /* If $maxMark is meaningful, ensure $mark never exceeds $maxMark
         */

        /* Otherwise, return null I guess?
         * You could make it arbitrarily 0/0 but that looks kinda weird
         */

        return [
            'mark' => $sanitisedMark,
            'max_mark' => $sanitisedMaxMark
        ];
    }
}
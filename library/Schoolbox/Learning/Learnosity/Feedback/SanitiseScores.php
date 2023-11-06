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
     * @param $inputScore
     * @param $inputMaxScore
     * @return array
     */
    public function sanitise($inputScore, $inputMaxScore): array
    {
        /* Double null
         */
        if (null === $inputScore && null === $inputMaxScore) {
            return [
                'score' => null,
                'max_score' => null
            ];
        }

        /* Someone is putting strings in here
         */
        if (in_array(trim((string)$inputScore), ['', 'e'], true)) {
            $score = null;

            if ('' === trim((string)$inputMaxScore)) {
                $maxScore = null;
            } else {
                $maxScore = (float)$inputMaxScore;
            }

            return [
                'score' => $score,
                'max_score' => $maxScore
            ];
        }

        /* These are numbers so we can process them as such
         * This should be *most* of the use cases
         */
        if (is_numeric($inputScore) && is_numeric($inputMaxScore)) {
            /* Artificially cast to floats which solves a few random problems
             */
            $score = round((float)$inputScore, 2);
            $maxScore = round((float)$inputMaxScore, 2);

            /* Negative values get rounded up to 0
             */
            if ($score < 0) {
                $score = 0.00;
            }

            if ($maxScore < 0) {
                $maxScore = 0.00;
            }

            /* Student got full marks; they can't get bonus marks anymore
             */
            if ($score > $maxScore) {
                return [
                    'score' => $maxScore,
                    'max_score' => $maxScore
                ];
            }
        }

        /* Default return scenario
         */
        return [
            'score' => null,
            'max_score' => null
        ];
    }
}
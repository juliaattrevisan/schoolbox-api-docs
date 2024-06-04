<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;

class Rating
{
    /**
     * The algorithm makes a special case usage
     */
    private const SEMESTER_1 = 1;
    private const SEMESTER_1_OFFSET = 0.5;


    /**
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Rate My Marks
     *
     * The boolean option $annotate forcibly appends the $reference and $diff
     * that were used in the calculations. This is helpful for debugging and
     * diagnosing how the rating was derived.
     *
     * @param int $studentYearLevel
     * @param int $currentSemester
     * @param float $mark
     * @param bool $annotate
     * @return string
     */
    public function rate(
        int $studentYearLevel,
        int $currentSemester,
        float $mark,
        bool $annotate = false
    ): string {
        $result = '';

        /* In first semester, "At Standard" is the stduent's year level -0.5
         * so in level 10, "At Standard" is measured from 9.5
         */
        $reference = $studentYearLevel;

        if (self::SEMESTER_1 === $currentSemester) {
            $reference -= self::SEMESTER_1_OFFSET;
        }

        /* Calculate proximity from your current expected standard
         */
        $diff = $mark - (float)$reference;

        if ($diff < -1) {
            $result = 'Well Below Standard';
        }

        if ($diff >= -1 && $diff < 0) {
            $result = 'Below Standard';
        }

        if ($diff === 0.0) {
            $result = 'At Standard';
        }

        if ($diff > 0 && $diff <= 1) {
            $result = 'Above Standard';
        }

        if ($diff > 1) {
            $result = 'Well Above Standard';
        }

        /* Add a metric annotation
         */
        if ($annotate) {
            $result .= sprintf(
                ' (ref: %s, diff: %s)',
                $reference,
                $diff
            );
        }

        return $result;
    }
}

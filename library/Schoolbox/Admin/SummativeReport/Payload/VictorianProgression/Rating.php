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
     *
     * @param array $submissionStateCodes
     */
    public function __construct(private array $submissionStateCodes)
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
     * @param string $mark
     * @param bool $annotate
     * @return string
     */
    public function rate(
        int $studentYearLevel,
        int $currentSemester,
        string $mark,
        bool $annotate = false
    ): string {
        $result = '';

        /* In first semester, "At Standard" is the student's year level -0.5
         * so in level 10, "At Standard" is measured from 9.5
         *
         * In second semester, "At Standard" is the student's year level
         * so in level 10, "At Standard" is measured from 10
         *
         * I call this "the reference value"
         */
        $reference = $studentYearLevel;

        if (self::SEMESTER_1 === $currentSemester) {
            $reference -= self::SEMESTER_1_OFFSET;
        }

        /* Deal with submission states first
         */
        if (in_array($mark, $this->submissionStateCodes, true)) {
            switch ($mark) {
                case 'DNPE': $result = 'Exemption from a Curriculum Area'; break;
                case 'DNPI': $result = 'Serious illness'; break;
                case 'DNPL': $result = 'Low participation'; break;
                case 'DNPP': $result = 'Late enrolment'; break;
                case 'DNPU': $result = 'Absent without reasonable excuse'; break;
            }

            /* Add a metric annotation
             */
            if ($annotate) {
                $result .= sprintf(
                    ' (ref: %s)',
                    $mark
                );
            }
        } else {
            /* Calculate proximity from your current expected standard
             */
            $diff = $mark - (float)$reference;

            if ($diff < -1) {
                $result = 'Well Below Standard';
            }

            if ($diff >= -1 && $diff < -0.5) {
                $result = 'Below Standard';
            }

            if ($diff >= -0.5 && $diff <= 0.0) {
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
        }

        return $result;
    }
}

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
     * There isn't enough room in schoolbox to store this properly, for now.
     * I have to hardcode a mapping between an outdated naming convention
     * and their human-readable meaning
     */
    public const SUBMISSION_STATE_MAP = [
        'DNPE' => 'Exemption from a Curriculum Area',
        'DNP-EX' => 'Exemption from a Curriculum Area',
        'DNPI' => 'Serious illness',
        'DNP-SI' => 'Serious illness',
        'DNPL' => 'Low participation',
        'DNP-LP' => 'Low participation',
        'DNPP' => 'Late enrolment',
        'DNP-LE' => 'Late enrolment',
        'DNPU' => 'Absent without reasonable excuse',
        'DNP-UN' => 'Absent without reasonable excuse',
    ];


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

        /* Deal with submission states first
         */
        if (in_array($mark, $this->submissionStateCodes, true)) {
            $result = self::SUBMISSION_STATE_MAP[$mark] ?? '';

            /* Add a metric annotation
             */
            if ($annotate) {
                $result .= sprintf(
                    ' (ref: %s)',
                    $mark
                );
            }

            return $result;
        }

        /* Otherwise, student received a numeric outcome.
         *
         * In first semester, "At Standard" is the student's year level -0.5
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

        /* Calculate proximity from your current expected standard
         */
        $diff = (float)$mark - (float)$reference;

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

        return $result;
    }
}

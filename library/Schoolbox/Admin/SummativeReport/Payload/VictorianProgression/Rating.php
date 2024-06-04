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
     * Constructor
     */
    public function __construct()
    {
    }


    /**
     * Rate My Marks
     *
     * @param int $studentYearLevel
     * @param float $mark
     * @return string
     */
    public function rate(int $studentYearLevel, float $mark): string
    {
        $diff = $mark - $studentYearLevel;

        if ($diff < -1) {
            return 'Well Below Standard';
        }

        if ($diff >= -1 && $diff < 0) {
            return 'Below Standard';
        }

        if ($diff === 0.0) {
            return 'At Standard';
        }

        if ($diff > 0 && $diff <= 1) {
            return 'Above Standard';
        }

        if ($diff > 1) {
            return 'Well Above Standard';
        }

        /* Not sure how you'd get here, but ok
         */
        return '';
    }
}

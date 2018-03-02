<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use RangeException;


/**
 * Permits fractional TotalMark suitable for Quiz.
 *
 * TotalMark CAN be mutated after construction. This is because
 * this class is intended for Quiz, where the $totalMark may vary depending on which questions
 * make up a QuizResponse
 *
 * On instantiation, the $totalMark passed to constructor is expected to be the sum
 * of marks available in all sections of the quiz.
 *
 * Before use, a caller must check if a response $totalMarks may differ, and if so, call setTotalMark()
 * The value passed in must be less than or equal to the original value
 *
 * eg, 'X / 3.75'
 *
 * @method null|float getTotalMark()
 */
class TotalMarkFractional extends TotalMarkAbstract
{
    const GRANULARITY_NUMERATOR   = 0.01;
    const GRANULARITY_DENOMINATOR = 0.01;


    /**
     * @inheritdoc
     */
    public function __construct($totalMark = null)
    {
        parent::__construct($totalMark);
    }

    /**
     * @inheritdoc
     * @throws \RangeException If $totalMark is negative
     */
    protected function rangeCheck($totalMark)
    {
        if($totalMark < 0){
            throw new \RangeException("Total mark cannot be negative: $totalMark");
        }
    }


    /**
     * Quiz marks are allowed to be outside the normal mark bounds.
     *
     * @param float $normalisedMark
     * @throws RangeException
     * @throws InvalidArgumentException If $normalisedMark isn't a numeric type
     */
    protected function validateNormalisedMark($normalisedMark)
    {
        $this->validateNormalisedMarkType($normalisedMark);
    }


    /* Getters / Setters
     */

    /**
     * @return float
     */
    protected function getGranularityNumerator()
    {
        return self::GRANULARITY_NUMERATOR;
    }

    /**
     * @return float
     */
    protected function getGranularityDenominator()
    {
        return self::GRANULARITY_DENOMINATOR;
    }
}

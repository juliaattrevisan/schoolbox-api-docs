<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        // Because certain assessment types rely on null total marks (i.e. quiz) we have to make sure we're not comparing a null value
        // or it will cause false positives due to type coercion
        if(null !== $totalMark && $totalMark <= 0){
            // Why the funny wording? It's because users will most likely encounter this in the ajax error on due work creation
            // which has certain...grammar requirements.
            // I'm also using the term "maximum available marks" to be consistent with the field this corresponds to on due work creation.
            // (The reason this will only be seen on due work creation is because other assessment types have a null total mark,
            // so wouldn't even execute this code path)
            throw new \RangeException('You must have maximum available marks greater than 0');
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

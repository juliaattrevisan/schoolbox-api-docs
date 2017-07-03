<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

/**
 * Only permits integer TotalMark (as opposed to fractional). Suitable for DueWork.
 *
 * TotalMark cannot be mutated after construction. This is because
 * this class is intended for DueWork, which have a constant $totalMark.
 *
 * eg '3.75 / 5'
 *
 * @method int getTotalMark()
 */
class TotalMarkWholeNumber extends TotalMarkAbstract
{
    const GRANULARITY_NUMERATOR   = 0.25;
    const GRANULARITY_DENOMINATOR = 1.00;

    public function __construct($totalMark)
    {
        // Is it an integer, or float with no fractional part?
        if (is_int($totalMark) || (is_float($totalMark) && 0.0 === $totalMark % 1.0)) {
            parent::__construct((int)$totalMark);
        } else {
            throw new \InvalidArgumentException("Total mark whole number must not contain fractional component: $totalMark");
        }
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

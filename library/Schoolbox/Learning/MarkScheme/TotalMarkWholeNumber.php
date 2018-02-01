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


    /**
     * @param float|int|string|null $totalMark
     * @throws \InvalidArgumentException If $totalMark is of wrong data type or contains fractional component
     * @throws \RangeException If $totalMark is negative
     */
    public function __construct($totalMark)
    {
        parent::__construct($this->validate($totalMark));
    }

    /**
     * @inheritdoc
     * @throws \RangeException If $totalMark is less than 1
     */
    protected function rangeCheck($totalMark)
    {
        if(null === $totalMark){
            return;
        }
        if($totalMark < 1){
            throw new \RangeException("Total mark cannot be less than 1: $totalMark");
        }
    }


    /**
     * Validates and returns the $totalMark as null or int
     *
     * Does not perform negative check.
     *
     * @param float|int|string|null $totalMark
     * @return null|int
     * @throws \InvalidArgumentException If $totalMark is invalid type or contains fractional component
     */
    private function validate($totalMark)
    {
        // null or numeric ?
        if(null === $totalMark || is_numeric($totalMark)){
            return $totalMark;
        }

        throw new \InvalidArgumentException("Total mark whole number must be null or numeric scalar: ($type)$totalMark");
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

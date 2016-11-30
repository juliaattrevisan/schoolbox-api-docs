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
    const GRANULARITY_DENOMINATOR = 1;

    const TOTAL_MARK_MIN = 1;

    /**
     * This type is comparable numerically.
     *
     * @var bool
     */
    protected $quantitative = true;

    /**
     * @param float $totalMark
     * @throws InvalidArgumentException if $totalMark has a fractional component
     * @throws RangeException if $totalMark is less than permitted minimum
     */
    protected function validateTotalMark($totalMark)
    {
        $intVal = intval($totalMark);
        if($totalMark != $intVal){
            throw new InvalidArgumentException("Total mark cannot have fractional part: $totalMark");
        }

        $totalMarkMin = $this->getTotalMarkMin();
        if($intVal < $totalMarkMin){
            if (!strlen($totalMark)) {
                $totalMark = 'none';
            }
            throw new RangeException("Total mark must be greater than or equal to $totalMarkMin: $totalMark given.");
        }
    }

    /* Getters / Setters
     */

    /**
     * Whole-Number TotalMark cannot have denominator reset after construction.
     *
     * @param int $totalMark
     * @throws \BadMethodCallException
     * @return void
     */
    public function setTotalMark($totalMark)
    {
        throw new \BadMethodCallException("Whole-Number TotalMark's denominator is not mutable: $totalMark");
    }

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

    /**
     * @return int
     */
    protected function getTotalMarkMin()
    {
        return self::TOTAL_MARK_MIN;
    }
}
